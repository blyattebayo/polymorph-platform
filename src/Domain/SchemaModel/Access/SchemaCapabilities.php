<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Access;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Access\CapabilitySet;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * Access-манифест домена: ресурс, определения capability, дефолтные роли и
 * route-требования — в одном файле.
 *
 * Ресурс 'schema' — корень дерева: он же родитель полевых ресурсов
 * schema.{id}.fields.* (см. SchemaFieldResources).
 */
final class SchemaCapabilities implements CapabilityDefinitionProvider
{
    public const RESOURCE = 'schema';

    public function capabilities(): array
    {
        return CapabilitySet::for(self::RESOURCE)
            ->read('Read schema fields')
            ->manage('Manage schemas')
            ->all();
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
