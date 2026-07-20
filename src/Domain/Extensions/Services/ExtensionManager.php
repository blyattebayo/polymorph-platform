<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

use Polymorph\Platform\Domain\Extensions\Core\Models\ExtensionRegistry;
use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\DiscoveredExtension;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionCapabilityService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionCompatibilityService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionMigrationService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionRouteService;
use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException as PluginException;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\HttpErrorException;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Polymorph\Sdk\Extension\ExtensionProvider as SdkV2ExtensionProvider;

final class ExtensionManager
{
    public function __construct(
        private readonly ExtensionDiscoveryService $discoveryService,
        private readonly ExtensionRegistryService $registryService,
        private readonly ExtensionRouteService $routeService,
        private readonly ExtensionCapabilityService $capabilityService,
        private readonly ExtensionCompatibilityService $compatibilityService,
        private readonly ExtensionMigrationService $migrationService,
        private readonly Application $app,
        private readonly AppLogger $logger,
        private readonly \Polymorph\Platform\Domain\Extensions\Trusted\TrustedExtensionSource $trusted,
    ) {}

    /**
     * @return list<DiscoveredExtension>
     */
    public function discoverAndSync(): array
    {
        $plugins = $this->discoveryService->discoverAll();

        // Trusted (withPlugins) plugins are Composer-shipped and always-on — they get NO
        // plugins_registry row / admin toggle (ADR 0006 Стадия 4). Still returned for listing.
        $syncable = array_values(array_filter(
            $plugins,
            fn (DiscoveredExtension $p): bool => ! $this->trusted->isTrusted($p->id),
        ));
        $this->registryService->syncDiscoveredPlugins($syncable);

        return $plugins;
    }

    /**
     * Идемпотентно провижинит все доверенные (withPlugins) плагины: миграции + ACL + onEnable-сидинг,
     * БЕЗ строки в plugins_registry (они always-on). Точка вызова — `plugins:sync-trusted` на деплое
     * (рядом с `migrate --force`). Безопасно гонять на каждом деплое (все шаги updateOrCreate/ensure).
     *
     * @return list<string> id провиженных расширений
     */
    public function provisionAllTrusted(): array
    {
        $provisioned = [];

        foreach ($this->discoveryService->discoverAll() as $plugin) {
            if (! $this->trusted->isTrusted($plugin->id)) {
                continue;
            }
            $this->provisionTrusted($plugin);
            $provisioned[] = $plugin->id;
        }

        return $provisioned;
    }

    /**
     * Побочки enable() для доверенного плагина, но БЕЗ markEnabled/already-enabled-гарда/DB-строки.
     * Транзакция как в enable(): миграции (DDL) + ACL + lifecycle hook откатываются вместе.
     */
    public function provisionTrusted(DiscoveredExtension $plugin): void
    {
        DB::transaction(function () use ($plugin): void {
            $this->migrationService->runMigrations($plugin);
            $this->capabilityService->ensureDefaultPolicies($plugin);
            $this->capabilityService->assignDefaultPluginAdminPolicy($plugin);
            $this->capabilityService->ensurePluginRoles($plugin);
            $this->fireLifecycleHook($plugin, 'firePluginEnabled');
        });
    }

    public function enable(string $pluginId): ExtensionRegistry
    {
        // Предпроверки до каких-либо мутаций: их провал не требует компенсации.
        $plugin = $this->requireDiscoveredPlugin($pluginId);
        $existing = $this->registryService->requireById($pluginId);

        if ((bool) $existing->is_enabled) {
            throw new PluginException("Plugin '{$pluginId}' already enabled.", ErrorCode::PLUGIN_ALREADY_ENABLED);
        }

        $this->assertCoreCompatibility($plugin, $existing);
        $this->assertDependenciesSatisfied($plugin);

        try {
            // Ранняя валидация роутов (поверхность + конфликты) до мутаций:
            // её провал фиксируется в last_error, плагин остаётся выключенным.
            // Сами роуты declarative — в БД ничего не пишется, компенсировать
            // нечего.
            $this->routeService->validate($plugin);

            $enabled = DB::transaction(function () use ($plugin, $pluginId): ExtensionRegistry {
                // Для PostgreSQL enable должен быть атомарным целиком:
                // миграции (DDL), ACL и lifecycle hook откатываются вместе.
                $this->migrationService->runMigrations($plugin);
                $this->capabilityService->ensureDefaultPolicies($plugin);
                $this->capabilityService->assignDefaultPluginAdminPolicy($plugin);
                $this->capabilityService->ensurePluginRoles($plugin);

                $enabled = $this->registryService->markEnabled($pluginId);
                $enabled->last_error = null;
                $enabled->save();

                $this->fireLifecycleHook($plugin, 'firePluginEnabled');

                return $enabled;
            });

            // Boot этого процесса прошёл без только что включённого плагина —
            // вживляем его роуты в живой роутер, чтобы они стали доступны сразу
            // (иначе 404 до следующего запроса/воркера).
            $this->routeService->registerInCurrentRouter($plugin);

            return $enabled;
        } catch (\Throwable $exception) {
            $this->storeLastError($pluginId, $exception);

            if ($exception instanceof ErrorConvertible || $exception instanceof HttpErrorException) {
                throw $exception;
            }

            // Оборачиваем неожиданный throwable в доменный PluginException
            // (ErrorConvertible) с исходной причиной как previous — её залогирует
            // унифицированный ErrorReporter (контекст 'exception' с полной цепочкой).
            throw new PluginException(
                "Failed to reload routes for plugin '{$pluginId}'.",
                ErrorCode::ROUTES_RELOAD_FAILED,
                previous: $exception,
            );
        }
    }

    public function disable(string $pluginId, bool $force = false): ExtensionRegistry
    {
        return DB::transaction(function () use ($pluginId, $force): ExtensionRegistry {
            $existing = $this->registryService->requireById($pluginId);
            if (! (bool) $existing->is_enabled) {
                throw new PluginException("Plugin '{$pluginId}' already disabled.", ErrorCode::PLUGIN_ALREADY_DISABLED);
            }

            if (! $force) {
                $this->assertNoEnabledDependents($pluginId);
            }

            $plugin = $this->findDiscoveredPlugin($pluginId);
            if ($plugin !== null) {
                $this->fireLifecycleHook($plugin, 'firePluginDisabled');
            }

            // Роуты declarative — в БД ничего не лежит. Плагин выпадает из дерева
            // сам: fingerprint кэша ротируется при смене is_enabled, остальные
            // воркеры пересоберут дерево уже без него.
            $disabled = $this->registryService->markDisabled($pluginId);
            $disabled->last_error = null;
            $disabled->save();

            return $disabled;
        });
    }

    public function update(string $pluginId): ExtensionRegistry
    {
        try {
            $plugin = $this->requireDiscoveredPlugin($pluginId);

            $entry = DB::transaction(function () use ($plugin, $pluginId): ExtensionRegistry {
                $warning = $this->compatibilityService->coreVersionIssue($plugin);
                $this->migrationService->runMigrations($plugin);
                $this->routeService->validate($plugin);
                $this->capabilityService->ensureDefaultPolicies($plugin);
                $this->capabilityService->assignDefaultPluginAdminPolicy($plugin);
                $this->capabilityService->ensurePluginRoles($plugin);
                $this->registryService->syncDiscoveredPlugins([$plugin]);

                // Хук обновления: даёт плагину пере-применить идемпотентный сидинг
                // (новые definitions, апгрейд контента) без отдельного re-enable.
                $this->fireLifecycleHook($plugin, 'firePluginUpdated');

                $entry = $this->registryService->requireById($pluginId);
                $entry->last_warning = $warning;
                $entry->last_error = null;
                $entry->save();

                return $entry;
            });

            // Вживляем роуты в живой роутер, если их там ещё нет (идемпотентно:
            // при уже включённом плагине registerInCurrentRouter увидит
            // существующие роуты и не продублирует).
            $this->routeService->registerInCurrentRouter($plugin);

            return $entry;
        } catch (\Throwable $exception) {
            $this->storeLastError($pluginId, $exception);

            if ($exception instanceof ErrorConvertible || $exception instanceof HttpErrorException) {
                throw $exception;
            }

            // Оборачиваем неожиданный throwable в доменный PluginException
            // (ErrorConvertible) с исходной причиной как previous — её залогирует
            // унифицированный ErrorReporter (контекст 'exception' с полной цепочкой).
            throw new PluginException(
                "Failed to reload routes for plugin '{$pluginId}'.",
                ErrorCode::ROUTES_RELOAD_FAILED,
                previous: $exception,
            );
        }
    }

    /**
     * Снимает плагин с установки: убирает его из реестра (ядро перестаёт его
     * знать). По умолчанию ДАННЫЕ СОХРАНЯЮТСЯ (таблицы остаются) — безопасно.
     * С $purge=true дополнительно дропает таблицы плагина и чистит репозиторий
     * миграций (явный снос данных).
     *
     * Если плагин включён — сначала корректно отключается (с проверкой
     * зависимых), затем удаляется. Файлы плагина на диске не трогаются (это
     * зона ответственности инсталлятора/распаковки).
     */
    public function uninstall(string $pluginId, bool $purge = false): void
    {
        $existing = $this->registryService->requireById($pluginId);

        $plugin = $this->findDiscoveredPlugin($pluginId);
        if ($purge && $plugin === null) {
            throw new PluginException(
                "Plugin '{$pluginId}' files are not present; cannot purge its data. Remove without --purge to drop the registry entry only.",
                ErrorCode::PLUGIN_NOT_FOUND,
            );
        }

        if ((bool) $existing->is_enabled) {
            // Переиспользуем disable: проверка включённых зависимых + lifecycle-хук.
            $this->disable($pluginId);
        }

        DB::transaction(function () use ($pluginId, $purge, $plugin): void {
            if ($purge && $plugin !== null) {
                // Postgres транзакционен по DDL — дроп таблиц откатится вместе с транзакцией.
                $this->migrationService->purge($plugin);
            }

            $this->registryService->deleteById($pluginId);
        });
    }

    /**
     * @return EloquentCollection<int, ExtensionRegistry>
     */
    public function listRegistry(): EloquentCollection
    {
        return $this->registryService->all();
    }

    private function assertCoreCompatibility(DiscoveredExtension $plugin, ExtensionRegistry $entry): void
    {
        $issue = $this->compatibilityService->coreVersionIssue($plugin);

        if ($issue === null) {
            $entry->last_warning = null;
            $entry->save();

            return;
        }

        if ($this->compatibilityService->isStrict()) {
            $entry->last_warning = $issue;
            $entry->save();
            throw new PluginException($issue, ErrorCode::PLUGIN_INCOMPATIBLE);
        }

        $entry->last_warning = $issue;
        $entry->save();
        $this->logger
            ->withContext(['plugin_id' => $plugin->id])
            ->warning('plugin.compatibility_warning', ['warning' => $issue]);
    }

    private function assertDependenciesSatisfied(DiscoveredExtension $plugin): void
    {
        foreach ($plugin->dependencies as $dependencyId => $range) {
            $dependency = $this->findDiscoveredPlugin($dependencyId);
            if ($dependency === null) {
                throw new PluginException(
                    "Plugin '{$plugin->id}' requires plugin '{$dependencyId}' which is not installed.",
                    ErrorCode::PLUGIN_DEPENDENCY_FAILED,
                );
            }

            $entry = $this->registryService->findById($dependencyId);
            if (! $entry instanceof ExtensionRegistry || ! (bool) $entry->is_enabled) {
                throw new PluginException(
                    "Plugin '{$plugin->id}' requires plugin '{$dependencyId}' to be enabled.",
                    ErrorCode::PLUGIN_DEPENDENCY_FAILED,
                );
            }

            if (! $this->compatibilityService->satisfies($dependency->version, $range)) {
                throw new PluginException(
                    "Plugin '{$plugin->id}' requires '{$dependencyId}' version '{$range}', installed is '{$dependency->version}'.",
                    ErrorCode::PLUGIN_DEPENDENCY_FAILED,
                );
            }
        }
    }

    private function assertNoEnabledDependents(string $pluginId): void
    {
        $dependents = [];

        foreach ($this->discoveryService->discoverAll() as $candidate) {
            if (! array_key_exists($pluginId, $candidate->dependencies)) {
                continue;
            }

            $entry = $this->registryService->findById($candidate->id);
            if ($entry instanceof ExtensionRegistry && (bool) $entry->is_enabled) {
                $dependents[] = $candidate->id;
            }
        }

        if ($dependents !== []) {
            throw new PluginException(
                "Plugin '{$pluginId}' is required by enabled plugin(s): ".implode(', ', $dependents).'. Disable them first or use force.',
                ErrorCode::PLUGIN_DEPENDENCY_FAILED,
            );
        }
    }

    /**
     * Вызов lifecycle-хука провайдера плагина (onEnable/onDisable через
     * публичные fire*-методы базового провайдера SDK).
     */
    private function fireLifecycleHook(DiscoveredExtension $plugin, string $method): void
    {
        $providerClass = $plugin->providerClass;
        if (! is_string($providerClass) || $providerClass === '') {
            return;
        }

        $provider = $this->app->getProvider($providerClass);

        // Extension SDK V2: lifecycle через fire*-методы провайдера.
        if ($provider instanceof SdkV2ExtensionProvider) {
            $v2method = match ($method) {
                'firePluginEnabled' => 'fireEnabled',
                'firePluginDisabled' => 'fireDisabled',
                'firePluginUpdated' => 'fireUpdated',
                default => null,
            };

            if ($v2method !== null) {
                $provider->{$v2method}();
            }
        }
    }

    private function requireDiscoveredPlugin(string $pluginId): DiscoveredExtension
    {
        $plugin = $this->findDiscoveredPlugin($pluginId);
        if ($plugin !== null) {
            return $plugin;
        }

        throw new PluginException("Plugin '{$pluginId}' not found.", ErrorCode::PLUGIN_NOT_FOUND);
    }

    private function findDiscoveredPlugin(string $pluginId): ?DiscoveredExtension
    {
        foreach ($this->discoveryService->discoverAll() as $plugin) {
            if ($plugin->id === $pluginId) {
                return $plugin;
            }
        }

        return null;
    }

    private function storeLastError(string $pluginId, \Throwable $exception): void
    {
        $entry = $this->registryService->findById($pluginId);
        if (! $entry instanceof ExtensionRegistry) {
            return;
        }

        $entry->last_error = $exception->getMessage();
        $entry->save();
    }
}
