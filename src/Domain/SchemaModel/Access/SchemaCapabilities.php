<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Access;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * Access-манифест домена: ресурс, определения capability и дефолтные роли —
 * в одном файле (раньше пара Capabilities + CapabilityProvider).
 *
 * Ресурс 'schema' — корень дерева: он же родитель полевых ресурсов
 * schema.{id}.fields.* (см. SchemaFieldResources).
 */
final class SchemaCapabilities implements CapabilityDefinitionProvider
{
    public const RESOURCE = 'schema';

    public function capabilities(): array
    {
        return [
            new CapabilityDefinition(self::RESOURCE, CapabilityCatalog::ACTION_READ, 'Read schema fields'),
            new CapabilityDefinition(self::RESOURCE, CapabilityCatalog::ACTION_MANAGE, 'Manage schemas'),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_SCHEMA_MANAGER => [
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_READ),
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_MANAGE),
            ],
            BuiltInRoleCatalog::ROLE_SCHEMA_READER => [
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_READ),
            ],
            BuiltInRoleCatalog::ROLE_RECORDS_EDITOR => [
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_READ),
            ],
            BuiltInRoleCatalog::ROLE_MEDIA_EDITOR => [
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_READ),
            ],
        ];
    }
}
