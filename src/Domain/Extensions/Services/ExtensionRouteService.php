<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException as PluginException;
use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\DiscoveredExtension;
use Polymorph\Platform\Domain\Routing\Core\Enums\OwnerType;
use Polymorph\Platform\Domain\Routing\Core\Models\RouteNode;
use Polymorph\Platform\Domain\Routing\Infrastructure\Loaders\PluginRouteLoader;
use Polymorph\Platform\Domain\Routing\Infrastructure\RouteRegistrar;

/**
 * Поддержка declarative-маршрутов плагинов: валидация поверхности/конфликтов
 * и вживление в живой роутер при enable/update. В БД ничего не пишется —
 * на boot роуты собираются из файлов через PluginRouteLoader.
 */
final class ExtensionRouteService
{
    public function __construct(
        private readonly ExtensionRouteConstraints $constraints,
        private readonly RouteRegistrar $routeRegistrar,
        private readonly PluginRouteLoader $pluginRouteLoader,
    ) {}

    public function registerInCurrentRouter(DiscoveredExtension $plugin): void
    {
        if ($plugin->backendRouteFile === null) {
            return;
        }

        /** @var mixed $config */
        $config = require $plugin->backendRouteFile;
        if (! is_array($config)) {
            return;
        }

        if ($this->currentRouterContainsRoutes($config)) {
            return;
        }

        $this->routeRegistrar->registerNodes($this->pluginRouteLoader->buildNodes($plugin->id, $config));
    }

    public function validate(DiscoveredExtension $plugin): void
    {
        if ($plugin->backendRouteFile === null) {
            return;
        }

        /** @var mixed $config */
        $config = require $plugin->backendRouteFile;
        if (! is_array($config)) {
            return;
        }

        foreach ($config as $node) {
            if (is_array($node)) {
                $this->constraints->assertAllowedRouteSurface($plugin->id, $node);
            }
        }

        $this->assertNoRouteConflicts($plugin->id, $config);
    }

    /**
     * @param  array<int, mixed>  $config
     */
    private function assertNoRouteConflicts(string $pluginId, array $config): void
    {
        $newRoutes = $this->collectRouteSurface($config, '');

        $seen = [];
        foreach ($newRoutes as [$method, $uri]) {
            $key = $method.' '.$uri;
            if (isset($seen[$key])) {
                throw new PluginException("Plugin '{$pluginId}' declares duplicate route '{$key}'.");
            }
            $seen[$key] = true;
        }

        $owner = OwnerType::make(OwnerType::PLUGIN, $pluginId);
        $existing = $this->existingRouteSurface($owner);

        foreach ($newRoutes as [$method, $uri]) {
            $key = $method.' '.$uri;
            if (isset($existing[$key])) {
                throw new PluginException(
                    "Plugin '{$pluginId}' route '{$key}' conflicts with existing route owned by '{$existing[$key]}'.",
                );
            }
        }
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @return list<array{string, string}>
     */
    private function collectRouteSurface(array $nodes, string $prefix): array
    {
        $routes = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $kind = $node['kind'] ?? null;
            $kindValue = $kind instanceof \BackedEnum ? (string) $kind->value : (is_string($kind) ? $kind : '');

            if ($kindValue === 'route') {
                $uri = is_string($node['uri'] ?? null) ? $node['uri'] : '';
                $fullUri = $this->joinPath($prefix, $uri);

                foreach ((array) ($node['methods'] ?? []) as $method) {
                    if (is_string($method)) {
                        $routes[] = [strtoupper($method), $fullUri];
                    }
                }
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $nodePrefix = is_string($node['prefix'] ?? null) ? $node['prefix'] : '';
                $routes = array_merge(
                    $routes,
                    $this->collectRouteSurface($node['children'], $this->joinPath($prefix, $nodePrefix)),
                );
            }
        }

        return $routes;
    }

    /**
     * @return array<string, string>
     */
    private function existingRouteSurface(string $excludeOwner): array
    {
        $nodes = RouteNode::query()
            ->get(['id', 'parent_id', 'kind', 'prefix', 'uri', 'methods', 'owner'])
            ->keyBy('id');

        $surface = [];

        foreach ($nodes as $node) {
            $kindValue = $node->kind instanceof \BackedEnum ? (string) $node->kind->value : (string) $node->kind;
            if ($kindValue !== 'route' || (string) $node->owner === $excludeOwner) {
                continue;
            }

            $segments = [is_string($node->uri) ? $node->uri : ''];
            $current = $node;
            while ($current->parent_id !== null && isset($nodes[$current->parent_id])) {
                $current = $nodes[$current->parent_id];
                if (is_string($current->prefix) && trim($current->prefix, '/') !== '') {
                    array_unshift($segments, $current->prefix);
                }
            }

            $fullUri = $this->joinPath(...$segments);

            foreach ((array) $node->methods as $method) {
                if (is_string($method)) {
                    $surface[strtoupper($method).' '.$fullUri] = (string) $node->owner;
                }
            }
        }

        return $surface;
    }

    private function joinPath(string ...$segments): string
    {
        $parts = [];
        foreach ($segments as $segment) {
            $trimmed = trim($segment, '/');
            if ($trimmed !== '') {
                $parts[] = $trimmed;
            }
        }

        return implode('/', $parts);
    }

    /**
     * @param  array<int, mixed>  $config
     */
    private function currentRouterContainsRoutes(array $config): bool
    {
        foreach ($this->routeNames($config) as $name) {
            if (Route::has($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @return list<string>
     */
    private function routeNames(array $nodes): array
    {
        $names = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $name = $node['name'] ?? null;
            if (is_string($name) && trim($name) !== '') {
                $names[] = trim($name);
            }

            $children = $node['children'] ?? null;
            if (is_array($children)) {
                array_push($names, ...$this->routeNames($children));
            }
        }

        return array_values(array_unique($names));
    }
}
