<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Access;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Access\CapabilitySet;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class SchemaCapabilities implements CapabilityDefinitionProvider
{
    public const RESOURCE = 'schema';

    public function capabilities(): array
    {
        return CapabilitySet::for(self::RESOURCE)->read('Read data schemas')->manage('Manage data schemas')->all();
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_SCHEMA_MANAGER => CapabilityCatalog::keys(self::RESOURCE, 'read', 'manage'),
            BuiltInRoleCatalog::ROLE_SCHEMA_READER => CapabilityCatalog::keys(self::RESOURCE, 'read'),
            BuiltInRoleCatalog::ROLE_RECORDS_EDITOR => CapabilityCatalog::keys(self::RESOURCE, 'read'),
            BuiltInRoleCatalog::ROLE_MEDIA_EDITOR => CapabilityCatalog::keys(self::RESOURCE, 'read'),
        ];
    }

    public static function requireRead(): string
    {
        return CapabilityCatalog::requirement(self::RESOURCE, CapabilityCatalog::ACTION_READ);
    }

    public static function requireManage(): string
    {
        return CapabilityCatalog::requirement(self::RESOURCE, CapabilityCatalog::ACTION_MANAGE);
    }
}
