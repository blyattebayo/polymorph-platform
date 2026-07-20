<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Access;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class RoutingCapabilityProvider implements CapabilityDefinitionProvider
{
    public function capabilities(): array
    {
        return [
            new CapabilityDefinition(RoutingCapabilities::ROUTE_MANAGE, CapabilityCatalog::ACTION_ACCESS, 'Manage routes'),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_ROUTING_MANAGER => [
                CapabilityCatalog::capabilityKey(RoutingCapabilities::ROUTE_MANAGE),
            ],
        ];
    }
}
