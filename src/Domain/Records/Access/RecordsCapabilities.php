<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Access;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * Access-манифест домена: ресурс, определения capability и дефолтные роли —
 * в одном файле (раньше пара Capabilities + CapabilityProvider).
 */
final class RecordsCapabilities implements CapabilityDefinitionProvider
{
    public const RESOURCE = 'records';

    public function capabilities(): array
    {
        return [
            new CapabilityDefinition(self::RESOURCE, CapabilityCatalog::ACTION_READ, 'Read records'),
            new CapabilityDefinition(self::RESOURCE, CapabilityCatalog::ACTION_WRITE, 'Write records'),
            new CapabilityDefinition(self::RESOURCE, CapabilityCatalog::ACTION_DELETE, 'Delete records'),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_RECORDS_EDITOR => [
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_READ),
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_WRITE),
            ],
            BuiltInRoleCatalog::ROLE_RECORDS_ADMIN => [
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_READ),
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_WRITE),
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_DELETE),
            ],
        ];
    }
}
