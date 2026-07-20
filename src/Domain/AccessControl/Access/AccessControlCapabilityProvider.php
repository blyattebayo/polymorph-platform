<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Access;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class AccessControlCapabilityProvider implements CapabilityDefinitionProvider
{
    public function capabilities(): array
    {
        return [
            new CapabilityDefinition(AccessControlCapabilities::POLICY_MANAGE, CapabilityCatalog::ACTION_ACCESS, 'Manage policies'),
            new CapabilityDefinition(AccessControlCapabilities::POLICY_ASSIGN, CapabilityCatalog::ACTION_ACCESS, 'Assign policies'),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_ACCESS_POLICY_MANAGER => [
                CapabilityCatalog::capabilityKey(AccessControlCapabilities::POLICY_MANAGE),
                CapabilityCatalog::capabilityKey(AccessControlCapabilities::POLICY_ASSIGN),
            ],
        ];
    }
}
