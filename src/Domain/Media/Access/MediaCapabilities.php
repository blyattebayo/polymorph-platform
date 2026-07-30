<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Access;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * Access-манифест домена: ресурс, определения capability и дефолтные роли —
 * в одном файле (раньше пара Capabilities + CapabilityProvider).
 */
final class MediaCapabilities implements CapabilityDefinitionProvider
{
    public const RESOURCE = 'media';

    public function capabilities(): array
    {
        return [
            new CapabilityDefinition(self::RESOURCE, CapabilityCatalog::ACTION_READ, 'Read media'),
            new CapabilityDefinition(self::RESOURCE, CapabilityCatalog::ACTION_WRITE, 'Write media'),
            new CapabilityDefinition(self::RESOURCE, CapabilityCatalog::ACTION_DELETE, 'Delete media'),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_MEDIA_EDITOR => [
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_READ),
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_WRITE),
            ],
            BuiltInRoleCatalog::ROLE_MEDIA_ADMIN => [
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_READ),
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_WRITE),
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_DELETE),
            ],
        ];
    }
}
