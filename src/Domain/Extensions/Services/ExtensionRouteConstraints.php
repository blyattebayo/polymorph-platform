<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException as PluginException;

final class ExtensionRouteConstraints
{
    /**
     * @param array<string, mixed> $routeNode
     */
    public function assertAllowedRouteSurface(string $pluginId, array $routeNode): void
    {
        $prefix = $this->extractNodePrefix($routeNode);
        if ($prefix === null) {
            return;
        }

        $normalized = trim($prefix, '/');

        $allowed = [
            sprintf((string) config('plugins.allowed_admin_prefix_template'), $pluginId),
            sprintf((string) config('plugins.allowed_api_prefix_template', 'api/v1/plugins/%s'), $pluginId),
            sprintf((string) config('plugins.allowed_web_prefix_template'), $pluginId),
            "api/v1/admin/ext/{$pluginId}",
            "api/v1/ext/{$pluginId}",
            "ext/{$pluginId}",
        ];

        foreach ($allowed as $prefixTemplate) {
            $candidate = trim($prefixTemplate, '/');
            if ($normalized === $candidate || str_starts_with($normalized . '/', $candidate . '/')) {
                return;
            }
        }

        throw new PluginException("Plugin '{$pluginId}' route prefix '{$normalized}' is outside allowed surface.");
    }

    /**
     * @param array<string, mixed> $routeNode
     */
    private function extractNodePrefix(array $routeNode): ?string
    {
        if (isset($routeNode['prefix']) && is_string($routeNode['prefix']) && trim($routeNode['prefix']) !== '') {
            return $routeNode['prefix'];
        }

        if (isset($routeNode['children']) && is_array($routeNode['children'])) {
            foreach ($routeNode['children'] as $child) {
                if (! is_array($child)) {
                    continue;
                }
                $prefix = $this->extractNodePrefix($child);
                if ($prefix !== null) {
                    return $prefix;
                }
            }
        }

        return null;
    }
}
