<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Access;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class RecordsCapabilityProvider implements CapabilityDefinitionProvider
{
    public function capabilities(): array
    {
        return [
            new CapabilityDefinition(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ, 'Read records'),
            new CapabilityDefinition(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_WRITE, 'Write records'),
            new CapabilityDefinition(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_DELETE, 'Delete records'),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_RECORDS_EDITOR => [
                CapabilityCatalog::capabilityKey(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ),
                CapabilityCatalog::capabilityKey(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_WRITE),
            ],
            BuiltInRoleCatalog::ROLE_RECORDS_ADMIN => [
                CapabilityCatalog::capabilityKey(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ),
                CapabilityCatalog::capabilityKey(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_WRITE),
                CapabilityCatalog::capabilityKey(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_DELETE),
            ],
        ];
    }
}
