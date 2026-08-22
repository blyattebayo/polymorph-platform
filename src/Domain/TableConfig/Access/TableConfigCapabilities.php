<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\TableConfig\Access;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Access\CapabilitySet;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class TableConfigCapabilities implements CapabilityDefinitionProvider
{
    public const RESOURCE = 'table_config';

    public function capabilities(): array
    {
        return CapabilitySet::for(self::RESOURCE)
            ->manage('Manage table configs')
            ->all();
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_TABLE_CONFIG_MANAGER => CapabilityCatalog::keys(
                self::RESOURCE,
                CapabilityCatalog::ACTION_MANAGE,
            ),
        ];
    }

    public static function requireManage(): string
    {
        return CapabilityCatalog::requirement(self::RESOURCE, CapabilityCatalog::ACTION_MANAGE);
    }
}
