<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Access;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\Domain\Extensions\Core\Models\ExtensionRegistry;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionDiscoveryService;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

final class ExtensionsCapabilityProvider implements CapabilityDefinitionProvider
{
    public function capabilities(): array
    {
        return [
            new CapabilityDefinition(ExtensionsCapabilities::RESOURCE, CapabilityCatalog::ACTION_MANAGE, 'Manage plugins'),
            ...$this->enabledPluginCapabilities(),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_PLUGINS_MANAGER => [
                CapabilityCatalog::capabilityKey(ExtensionsCapabilities::RESOURCE, CapabilityCatalog::ACTION_MANAGE),
            ],
        ];
    }

    /**
     * @return list<CapabilityDefinition>
     */
    private function enabledPluginCapabilities(): array
    {
        if (! class_exists(ExtensionRegistry::class) || ! class_exists(ExtensionDiscoveryService::class)) {
            return [];
        }

        try {
            /** @var list<string> $enabledPluginIds */
            $enabledPluginIds = ExtensionRegistry::query()
                ->where('is_enabled', true)
                ->pluck('plugin_id')
                ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '')
                ->values()
                ->all();

            if ($enabledPluginIds === []) {
                return [];
            }

            $enabledLookup = array_fill_keys($enabledPluginIds, true);
            $definitions = [];

            foreach (app(ExtensionDiscoveryService::class)->discoverAll() as $plugin) {
                if (! isset($enabledLookup[$plugin->id])) {
                    continue;
                }

                foreach ($plugin->capabilityDefinitions as $capability) {
                    $definitions[] = new CapabilityDefinition(
                        resource: $capability->resource,
                        action: $capability->action,
                        label: $capability->label,
                    );
                }
            }

            return $definitions;
        } catch (\Throwable $exception) {
            // Каталог не должен падать из-за одного битого плагина, но и молчать
            // нельзя: раньше capability плагинов просто исчезали из /auth/current
            // без следа в логах (аудит, C5).
            app(AppLogger::class)->error('extensions.capability_catalog_failed', [
                'exception' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}
