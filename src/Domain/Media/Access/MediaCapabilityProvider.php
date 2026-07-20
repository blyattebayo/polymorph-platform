<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Access;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class MediaCapabilityProvider implements CapabilityDefinitionProvider
{
    public function capabilities(): array
    {
        return [
            new CapabilityDefinition(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ, 'Read media'),
            new CapabilityDefinition(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_WRITE, 'Write media'),
            new CapabilityDefinition(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_DELETE, 'Delete media'),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_MEDIA_EDITOR => [
                CapabilityCatalog::capabilityKey(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ),
                CapabilityCatalog::capabilityKey(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_WRITE),
            ],
            BuiltInRoleCatalog::ROLE_MEDIA_ADMIN => [
                CapabilityCatalog::capabilityKey(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ),
                CapabilityCatalog::capabilityKey(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_WRITE),
                CapabilityCatalog::capabilityKey(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_DELETE),
            ],
        ];
    }
}
