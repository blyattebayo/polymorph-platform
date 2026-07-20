<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\TableConfig\Access;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class TableConfigCapabilityProvider implements CapabilityDefinitionProvider
{
    public function capabilities(): array
    {
        return [
            new CapabilityDefinition(TableConfigCapabilities::MANAGE, CapabilityCatalog::ACTION_ACCESS, 'Manage table configs'),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_TABLE_CONFIG_MANAGER => [
                CapabilityCatalog::capabilityKey(TableConfigCapabilities::MANAGE),
            ],
        ];
    }
}
