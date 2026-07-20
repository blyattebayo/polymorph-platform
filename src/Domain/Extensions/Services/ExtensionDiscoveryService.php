<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException as PluginException;
use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\DiscoveredExtension;
use Polymorph\Platform\Domain\Extensions\Manifest\ManifestV2Validator;
use Polymorph\Platform\Domain\Extensions\Trusted\TrustedExtensionSource;

final class ExtensionDiscoveryService
{
    /** Манифест расширения v2 (Extension SDK V2). */
    private const MANIFEST_V2 = 'extension.json';

    public function __construct(
        private readonly ExtensionManifestValidator $validator,
        private readonly ExtensionAclManifestParser $aclManifestParser,
        private readonly ManifestV2Validator $manifestV2Validator,
        private readonly TrustedExtensionSource $trusted,
    ) {}

    /**
     * @return list<DiscoveredExtension>
     */
    public function discoverAll(): array
    {
        $manifestFile = (string) config('plugins.manifest_file', 'plugin.json');

        $plugins = [];

        // Рантайм drop-in плагины (магазинные) из plugins.root_path.
        $rootPath = (string) config('plugins.root_path');
        if ($rootPath !== '' && is_dir($rootPath)) {
            $directories = glob($rootPath.'/*', GLOB_ONLYDIR) ?: [];
            sort($directories);

            foreach ($directories as $directory) {
                $directoryName = basename($directory);
                if (is_string($directoryName) && str_starts_with($directoryName, '_')) {
                    continue;
                }

                $plugin = $this->loadFromDirectory($directory, $manifestFile);
                if ($plugin !== null) {
                    $plugins[] = $plugin;
                }
            }
        }

        // Доверенные плагины (Composer-пакеты, withPlugins). Обрабатываются ВСЕГДА, даже если
        // root_path пуст; добавляются ПОСЛЕ drop-in, поэтому при совпадении id имеют приоритет
        // (sortByDependencies дедуплицирует по id — последний побеждает). ADR 0006 Стадия 4.
        foreach ($this->trusted->directories() as $directory) {
            $plugin = $this->loadFromDirectory($directory, $manifestFile);
            if ($plugin !== null) {
                $plugins[] = $plugin;
            }
        }

        return $this->sortByDependencies($plugins);
    }

    /**
     * Грузит одно расширение из произвольного каталога (drop-in или vendor-пакет). V1 (plugin.json)
     * имеет приоритет для обратной совместимости; иначе — V2 (extension.json). Каталог
     * location-agnostic: всё резолвится относительно $directory.
     */
    private function loadFromDirectory(string $directory, string $manifestFile): ?DiscoveredExtension
    {
        $manifestPath = $directory.DIRECTORY_SEPARATOR.$manifestFile;
        $v2ManifestPath = $directory.DIRECTORY_SEPARATOR.self::MANIFEST_V2;

        if (is_file($manifestPath)) {
            return $this->loadPlugin($manifestPath, $directory);
        }

        if (is_file($v2ManifestPath)) {
            return $this->loadExtensionV2($v2ManifestPath, $directory);
        }

        return null;
    }

    /**
     * Топологическая сортировка по dependencies: зависимости загружаются
     * раньше зависимых (детерминированно, алфавитный порядок внутри уровня).
     *
     * @param  list<DiscoveredExtension>  $plugins
     * @return list<DiscoveredExtension>
     *
     * @throws PluginException при циклической зависимости
     */
    private function sortByDependencies(array $plugins): array
    {
        $byId = [];
        foreach ($plugins as $plugin) {
            $byId[$plugin->id] = $plugin;
        }

        $sorted = [];
        $visited = [];
        $inProgress = [];

        $visit = function (string $pluginId) use (&$visit, &$sorted, &$visited, &$inProgress, $byId): void {
            if (isset($visited[$pluginId])) {
                return;
            }

            if (isset($inProgress[$pluginId])) {
                throw new PluginException("Cyclic plugin dependency detected involving '{$pluginId}'.");
            }

            $inProgress[$pluginId] = true;

            foreach (array_keys($byId[$pluginId]->dependencies) as $dependencyId) {
                if (isset($byId[$dependencyId])) {
                    $visit($dependencyId);
                }
            }

            unset($inProgress[$pluginId]);
            $visited[$pluginId] = true;
            $sorted[] = $byId[$pluginId];
        };

        foreach (array_keys($byId) as $pluginId) {
            $visit($pluginId);
        }

        return $sorted;
    }

    /**
     * @throws PluginException
     */
    private function loadPlugin(string $manifestPath, string $directory): DiscoveredExtension
    {
        $raw = file_get_contents($manifestPath);
        if (! is_string($raw) || trim($raw) === '') {
            throw new PluginException("Manifest {$manifestPath}: file is empty.");
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new PluginException("Manifest {$manifestPath}: invalid JSON.");
        }

        $this->validator->validate($decoded, $manifestPath);
        $this->assertControllerAutoloadContract($decoded, $manifestPath, $directory);

        $pluginId = (string) $decoded['id'];
        $routeFile = data_get($decoded, 'backend.routes.file');
        $routeFilePath = is_string($routeFile) && trim($routeFile) !== ''
            ? $directory.DIRECTORY_SEPARATOR.ltrim($routeFile, '/\\')
            : $directory.DIRECTORY_SEPARATOR.'be/routes.php';

        if (! is_file($routeFilePath)) {
            $routeFilePath = null;
        }

        // Роуты плагина всегда declarative: грузятся в память из be/routes.php
        // на boot (PluginRouteLoader), без записи в route_nodes. Прежний db-режим
        // удалён — стейл-манифест с ним отклоняем громко, а не молча игнорируем.
        $routeMode = data_get($decoded, 'backend.routes.mode');
        if ($routeMode !== null && $routeMode !== 'declarative') {
            throw new PluginException(
                "Manifest {$manifestPath}: backend.routes.mode '{$routeMode}' is no longer supported; plugin routes are declarative (omit the field).",
            );
        }

        $frontendNavSection = data_get($decoded, 'frontend.navigation.section');
        if (is_string($frontendNavSection) && trim($frontendNavSection) !== '') {
            $frontendNavSection = trim($frontendNavSection);
            if (! in_array($frontendNavSection, ['content', 'system'], true)) {
                throw new PluginException(
                    "Manifest {$manifestPath}: frontend.navigation.section '{$frontendNavSection}' is invalid; expected 'content' or 'system'.",
                );
            }
        } else {
            $frontendNavSection = null;
        }

        $this->validateFrontendUiMode(data_get($decoded, 'frontend.ui.mode'), $manifestPath);

        return new DiscoveredExtension(
            id: $pluginId,
            name: (string) $decoded['name'],
            version: (string) $decoded['version'],
            coreVersionRange: (string) $decoded['coreVersionRange'],
            tablePrefix: (string) data_get($decoded, 'db.tablePrefix'),
            manifestPath: $manifestPath,
            manifestHash: hash('sha256', $raw),
            providerClass: is_string(data_get($decoded, 'backend.provider')) ? (string) data_get($decoded, 'backend.provider') : null,
            backendRouteFile: $routeFilePath,
            capabilityDefinitions: $this->aclManifestParser->parseCapabilities($decoded, $pluginId),
            defaultAdminRoles: $this->aclManifestParser->parseDefaultAdminRoles($decoded),
            frontendBundle: is_string(data_get($decoded, 'frontend.bundle')) ? (string) data_get($decoded, 'frontend.bundle') : null,
            frontendMountPath: is_string(data_get($decoded, 'frontend.mountPath')) ? (string) data_get($decoded, 'frontend.mountPath') : null,
            frontendNavTitle: is_string(data_get($decoded, 'frontend.navigation.title')) ? (string) data_get($decoded, 'frontend.navigation.title') : null,
            frontendNavSection: $frontendNavSection,
            dependencies: $this->parseDependencies($decoded),
            pluginRoles: $this->aclManifestParser->parseRoles($decoded, $pluginId),
        );
    }

    /**
     * Загружает расширение V2 (extension.json) в ту же модель {@see DiscoveredExtension},
     * что и V1 — чтобы зрелый downstream (routes/ACL/migrations/lifecycle) работал без
     * plugin.json. Валидация и согласование версии SDK — через {@see ManifestV2Validator};
     * capabilities — в namespace `ext.{id}.` (см. ADR 0003).
     *
     * @throws PluginException
     */
    private function loadExtensionV2(string $manifestPath, string $directory): DiscoveredExtension
    {
        $raw = file_get_contents($manifestPath);
        if (! is_string($raw) || trim($raw) === '') {
            throw new PluginException("Manifest {$manifestPath}: file is empty.");
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new PluginException("Manifest {$manifestPath}: invalid JSON.");
        }

        try {
            $manifest = $this->manifestV2Validator->validate($decoded, $manifestPath);
        } catch (\InvalidArgumentException $e) {
            throw new PluginException("Manifest {$manifestPath}: {$e->getMessage()}");
        }

        $pluginId = $manifest->id;
        $contributes = $manifest->contributes;

        // Роуты v2 декларативны и всегда лежат в be/routes.php (Routes::for(...)).
        $routeFilePath = $directory.DIRECTORY_SEPARATOR.'be/routes.php';
        if (! is_file($routeFilePath)) {
            $routeFilePath = null;
        }

        $frontend = is_array($contributes['frontend'] ?? null) ? $contributes['frontend'] : [];
        $navigation = is_array($contributes['navigation'] ?? null) ? $contributes['navigation'] : [];

        $navSection = is_string($navigation['section'] ?? null) ? trim((string) $navigation['section']) : null;
        if ($navSection !== null && $navSection !== '' && ! in_array($navSection, ['content', 'system'], true)) {
            throw new PluginException(
                "Manifest {$manifestPath}: contributes.navigation.section '{$navSection}' is invalid; expected 'content' or 'system'.",
            );
        }
        $navSection = ($navSection === null || $navSection === '') ? null : $navSection;

        $this->validateFrontendUiMode($frontend['ui']['mode'] ?? null, $manifestPath);

        $coreVersionRange = data_get($decoded, 'coreVersionRange');
        if (! is_string($coreVersionRange) || trim($coreVersionRange) === '') {
            $coreVersionRange = '*';
        } else {
            $coreVersionRange = trim($coreVersionRange);
        }
        $tablePrefix = data_get($decoded, 'db.tablePrefix');
        if (! is_string($tablePrefix) || trim($tablePrefix) === '') {
            $tablePrefix = '';
        } else {
            $tablePrefix = trim($tablePrefix);
        }

        return new DiscoveredExtension(
            id: $pluginId,
            name: $manifest->name,
            version: $manifest->version,
            // V2 поддерживает опциональную host-core ось совместимости через
            // coreVersionRange (используется в hardening/compatibility сценариях).
            coreVersionRange: $coreVersionRange,
            // V2 обычно работает без собственных таблиц, но может объявлять
            // db.tablePrefix для миграций/очистки данных.
            tablePrefix: $tablePrefix,
            manifestPath: $manifestPath,
            manifestHash: hash('sha256', $raw),
            providerClass: $manifest->provider,
            backendRouteFile: $routeFilePath,
            capabilityDefinitions: $this->aclManifestParser->parseV2Capabilities($contributes, $pluginId),
            defaultAdminRoles: $this->aclManifestParser->parseV2DefaultAdminRoles($contributes),
            frontendBundle: is_string($frontend['bundle'] ?? null) ? (string) $frontend['bundle'] : null,
            frontendMountPath: is_string($frontend['mountPath'] ?? null) ? (string) $frontend['mountPath'] : null,
            frontendNavTitle: is_string($navigation['title'] ?? null) ? (string) $navigation['title'] : null,
            frontendNavSection: $navSection,
            dependencies: $this->parseDependencies($decoded),
            pluginRoles: $this->aclManifestParser->parseV2Roles($contributes, $pluginId),
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, string>
     */
    private function parseDependencies(array $manifest): array
    {
        $raw = $manifest['dependencies'] ?? null;
        if (! is_array($raw)) {
            return [];
        }

        $dependencies = [];
        foreach ($raw as $dependencyId => $range) {
            if (is_string($dependencyId) && is_string($range)) {
                $dependencies[$dependencyId] = $range;
            }
        }

        return $dependencies;
    }

    /**
     * Режим отображения плагина в админ-SPA: overlay (по умолчанию) — полноэкранный
     * модальный оверлей поверх ядра; embedded — inline в общем лейауте. Поле
     * опциональное; пустое/отсутствующее значение silently fallback'ит к overlay.
     */
    private function validateFrontendUiMode(mixed $mode, string $manifestPath): void
    {
        if ($mode === null) {
            return;
        }
        if (! is_string($mode) || trim($mode) === '') {
            return;
        }
        $mode = trim($mode);
        if (! in_array($mode, ['overlay', 'embedded'], true)) {
            throw new PluginException(
                "Manifest {$manifestPath}: frontend.ui.mode '{$mode}' is invalid; expected 'overlay' or 'embedded'.",
            );
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function assertControllerAutoloadContract(array $manifest, string $manifestPath, string $directory): void
    {
        $autoloadBase = rtrim(str_replace('\\', '/', $directory), '/');
        $psr4Path = data_get($manifest, 'backend.psr4Path');
        if ($psr4Path === null) {
            return;
        }

        if (! is_string($psr4Path) || trim($psr4Path) === '') {
            throw new PluginException("Manifest {$manifestPath}: backend.psr4Path must be non-empty string when provided.");
        }

        $resolvedPath = $autoloadBase.'/'.ltrim(str_replace('\\', '/', $psr4Path), '/');
        if (! is_dir($resolvedPath)) {
            throw new PluginException("Manifest {$manifestPath}: backend.psr4Path '{$psr4Path}' does not exist.");
        }
    }
}
