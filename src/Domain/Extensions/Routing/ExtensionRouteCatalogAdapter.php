<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Routing;

use Polymorph\Platform\Domain\Extensions\Core\Models\ExtensionRegistry;
use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\DiscoveredExtension;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionDiscoveryService;
use Polymorph\Platform\Domain\Routing\Core\Contracts\PluginRouteCatalog;
use Polymorph\Platform\Domain\Routing\Core\Contracts\PluginRouteSet;

final class ExtensionRouteCatalogAdapter implements PluginRouteCatalog
{
    public function __construct(
        private readonly ExtensionDiscoveryService $discovery,
    ) {}

    /**
     * @return list<PluginRouteSet>
     */
    public function enabledRouteSets(): array
    {
        $sets = [];

        foreach ($this->enabledPlugins() as $plugin) {
            $config = $this->loadConfig($plugin);
            if ($config === []) {
                continue;
            }
            $sets[] = new PluginRouteSet($plugin->id, $config);
        }

        return $sets;
    }

    public function fingerprint(): string
    {
        $parts = [];

        foreach ($this->enabledPlugins() as $plugin) {
            $mtime = ($plugin->backendRouteFile !== null && is_file($plugin->backendRouteFile))
                ? (string) (filemtime($plugin->backendRouteFile) ?: 0)
                : '0';
            $parts[] = $plugin->id.':'.$plugin->manifestHash.':'.$mtime;
        }

        sort($parts);

        return substr(sha1(implode('|', $parts)), 0, 12);
    }

    /**
     * @return list<DiscoveredExtension>
     */
    private function enabledPlugins(): array
    {
        $enabled = $this->enabledPluginIds();

        $plugins = [];
        foreach ($this->discovery->discoverAll() as $plugin) {
            if (! isset($enabled[$plugin->id])) {
                continue;
            }
            if ($plugin->backendRouteFile === null) {
                continue;
            }
            $plugins[] = $plugin;
        }

        return $plugins;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadConfig(DiscoveredExtension $plugin): array
    {
        if ($plugin->backendRouteFile === null || ! is_file($plugin->backendRouteFile)) {
            return [];
        }

        /** @var mixed $config */
        $config = require $plugin->backendRouteFile;

        return is_array($config) ? $config : [];
    }

    /**
     * @return array<string, true>
     */
    private function enabledPluginIds(): array
    {
        try {
            $ids = ExtensionRegistry::query()
                ->where('is_enabled', true)
                ->pluck('plugin_id')
                ->all();
        } catch (\Throwable) {
            return [];
        }

        $map = [];
        foreach ($ids as $id) {
            if (is_string($id)) {
                $map[$id] = true;
            }
        }

        return $map;
    }
}
