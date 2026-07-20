<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Access;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class SchemaCapabilityProvider implements CapabilityDefinitionProvider
{
    public function capabilities(): array
    {
        return [
            new CapabilityDefinition(SchemaCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ, 'Read schema fields'),
            new CapabilityDefinition(SchemaCapabilities::MANAGE, CapabilityCatalog::ACTION_ACCESS, 'Manage schemas'),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_SCHEMA_MANAGER => [
                CapabilityCatalog::capabilityKey(SchemaCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ),
                CapabilityCatalog::capabilityKey(SchemaCapabilities::MANAGE, CapabilityCatalog::ACTION_ACCESS),
            ],
            BuiltInRoleCatalog::ROLE_SCHEMA_READER => [
                CapabilityCatalog::capabilityKey(SchemaCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ),
            ],
            BuiltInRoleCatalog::ROLE_RECORDS_EDITOR => [
                CapabilityCatalog::capabilityKey(SchemaCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ),
            ],
            BuiltInRoleCatalog::ROLE_MEDIA_EDITOR => [
                CapabilityCatalog::capabilityKey(SchemaCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ),
            ],
        ];
    }
}
