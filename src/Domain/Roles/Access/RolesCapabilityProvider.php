<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Access;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class RolesCapabilityProvider implements CapabilityDefinitionProvider
{
    public function capabilities(): array
    {
        return [
            new CapabilityDefinition(RolesCapabilities::READ, CapabilityCatalog::ACTION_ACCESS, 'Read roles'),
            new CapabilityDefinition(RolesCapabilities::LIFECYCLE, CapabilityCatalog::ACTION_ACCESS, 'Manage roles'),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_USERS_MANAGER => [
                CapabilityCatalog::capabilityKey(RolesCapabilities::READ),
                CapabilityCatalog::capabilityKey(RolesCapabilities::LIFECYCLE),
            ],
        ];
    }
}
