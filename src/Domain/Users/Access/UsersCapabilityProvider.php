<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Access;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class UsersCapabilityProvider implements CapabilityDefinitionProvider
{
    public function capabilities(): array
    {
        return [
            new CapabilityDefinition(UsersCapabilities::READ, CapabilityCatalog::ACTION_ACCESS, 'Read users'),
            new CapabilityDefinition(UsersCapabilities::LIFECYCLE, CapabilityCatalog::ACTION_ACCESS, 'Manage users'),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_USERS_MANAGER => [
                CapabilityCatalog::capabilityKey(UsersCapabilities::READ),
                CapabilityCatalog::capabilityKey(UsersCapabilities::LIFECYCLE),
            ],
        ];
    }
}
