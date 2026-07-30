<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\TableConfig\Access;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * Access-манифест домена: ресурс, определения capability и дефолтные роли —
 * в одном файле (раньше пара Capabilities + CapabilityProvider).
 */
final class TableConfigCapabilities implements CapabilityDefinitionProvider
{
    public const RESOURCE = 'table_config';

    public function capabilities(): array
    {
        return [
            new CapabilityDefinition(self::RESOURCE, CapabilityCatalog::ACTION_MANAGE, 'Manage table configs'),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_TABLE_CONFIG_MANAGER => [
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_MANAGE),
            ],
        ];
    }
}
