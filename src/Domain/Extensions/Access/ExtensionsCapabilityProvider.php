<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Access;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionDiscoveryService;

final class ExtensionsCapabilityProvider implements CapabilityDefinitionProvider
{
    public function capabilities(): array
    {
        return [
            ...$this->pluginCapabilities(),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        return [];
    }

    /**
     * @return list<CapabilityDefinition>
     */
    private function pluginCapabilities(): array
    {
        $definitions = [];
        foreach (app(ExtensionDiscoveryService::class)->discoverAll() as $plugin) {
            foreach ($plugin->capabilityDefinitions as $capability) {
                $definitions[] = new CapabilityDefinition(
                    resource: $capability->resource,
                    action: $capability->action,
                    label: $capability->label,
                );
            }
        }

        return $definitions;
    }
}
