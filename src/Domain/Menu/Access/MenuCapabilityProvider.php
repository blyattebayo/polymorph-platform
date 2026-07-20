<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Menu\Access;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class MenuCapabilityProvider implements CapabilityDefinitionProvider
{
    public function capabilities(): array
    {
        return [
            new CapabilityDefinition(MenuCapabilities::MANAGE, CapabilityCatalog::ACTION_ACCESS, 'Manage navigation menu'),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN => [
                CapabilityCatalog::capabilityKey(MenuCapabilities::MANAGE),
            ],
        ];
    }
}
