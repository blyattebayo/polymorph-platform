<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\Extensions\Access\ExtensionsCapabilityProvider;
use Polymorph\Platform\Domain\Extensions\Events\EloquentRecordDefinitionSchemaCode;
use Polymorph\Platform\Domain\Extensions\Events\RecordDefinitionSchemaCode;
use Polymorph\Platform\Domain\Extensions\Events\RecordLifecycleSdkBridge;
use Polymorph\Platform\Domain\Extensions\Routing\ExtensionRouteFileCatalog;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionAclManifestParser;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionAutoloadService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionCapabilityService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionCompatibilityService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionDiscoveryService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionFrontendManifestService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionManager;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionManifestValidator;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionMigrationService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionRegistryService;
use Polymorph\Platform\Domain\Records\Events\RecordDeleted;
use Polymorph\Platform\Domain\Routing\Plugin\PluginRouteCatalog;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Polymorph\Platform\Support\Logging\Contracts\SecretRedactor;
use Polymorph\Platform\Support\Logging\PayloadRedactor;
use Polymorph\Sdk\Extension\ExtensionProvider as SdkV2ExtensionProvider;
use Throwable;

final class ExtensionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExtensionAclManifestParser::class);
        $this->app->singleton(ExtensionManifestValidator::class);
        $this->app->singleton(ExtensionDiscoveryService::class);
        $this->app->singleton(ExtensionRegistryService::class);
        $this->app->singleton(ExtensionCapabilityService::class);
        $this->app->singleton(ExtensionCompatibilityService::class);
        $this->app->singleton(ExtensionMigrationService::class);
        $this->app->singleton(ExtensionFrontendManifestService::class);
        $this->app->singleton(ExtensionManager::class);
        $this->app->singleton(ExtensionAutoloadService::class);

        $this->app->singleton(SecretRedactor::class, PayloadRedactor::class);

        // SDK event bridge: platform-internal record events -> declared Polymorph\Sdk\Events\*
        // contract that extensions subscribe to (ADR 0005 Фаза 4).
        $this->app->singleton(RecordDefinitionSchemaCode::class, EloquentRecordDefinitionSchemaCode::class);

        // Каталог файлов маршрутов включённых расширений. Порт объявлен в
        // домене Routing, реализация живёт здесь: роутинг не знает, откуда
        // берётся список расширений, а Extensions не знает, как их монтируют.
        $this->app->singleton(
            PluginRouteCatalog::class,
            ExtensionRouteFileCatalog::class,
        );

        $this->app->make(ExtensionAutoloadService::class)->registerAutoload();
        $this->registerExtensionProviders();
    }

    public function boot(): void
    {
        foreach ($this->app->make(ExtensionMigrationService::class)->discoverMigrationPaths() as $migrationPath) {
            $this->loadMigrationsFrom($migrationPath);
        }

        $this->app->tag([ExtensionsCapabilityProvider::class], 'access.capability_providers');

        // Re-emit internal record-lifecycle events as the SDK contract for extension listeners.
        Event::listen(RecordDeleted::class, RecordLifecycleSdkBridge::class);
    }

    /**
     * Страховка бутстрапа: обход каталога пропускает битые расширения
     * поштучно, но цикл в зависимостях — свойство НАБОРА, а не одного
     * расширения, и всё ещё бросает. Приложение обязано подниматься в любом
     * случае: без расширений можно работать и чинить, без приложения — нет.
     */
    private function registerExtensionProviders(): void
    {
        try {
            $extensions = $this->app->make(ExtensionDiscoveryService::class)->discoverAll();
        } catch (Throwable $exception) {
            $this->app->make(AppLogger::class)->error('extensions.discovery_aborted', [
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        foreach ($extensions as $extension) {
            $providerClass = $extension->providerClass;
            if (! is_string($providerClass) || $providerClass === '') {
                continue;
            }

            if (! class_exists($providerClass) || ! is_subclass_of($providerClass, ServiceProvider::class)) {
                continue;
            }

            $provider = new $providerClass($this->app);

            // Провайдер обязан представляться тем же id, что и манифест его
            // каталога: иначе он получил бы scoped-сервисы (данные, гранты)
            // чужого расширения через $this->records()/grants().
            if ($provider instanceof SdkV2ExtensionProvider
                && $provider->declaredExtensionId() !== $extension->id) {
                $this->app->make(AppLogger::class)->error('extensions.provider_id_mismatch', [
                    'manifest_id' => $extension->id,
                    'declared_id' => $provider->declaredExtensionId(),
                    'provider' => $providerClass,
                ]);

                continue;
            }

            $this->app->register($provider);
        }
    }
}
