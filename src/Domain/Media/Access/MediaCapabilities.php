<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Access;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Access\CapabilitySet;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * Access-манифест домена: ресурс, определения capability, дефолтные роли и
 * route-требования — в одном файле.
 */
final class MediaCapabilities implements CapabilityDefinitionProvider
{
    public const RESOURCE = 'media';

    public function capabilities(): array
    {
        return CapabilitySet::for(self::RESOURCE)
            ->read('Read media')
            ->write('Write media')
            ->delete('Delete media')
            ->all();
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_MEDIA_EDITOR => CapabilityCatalog::keys(self::RESOURCE, 'read', 'write'),
            BuiltInRoleCatalog::ROLE_MEDIA_ADMIN => CapabilityCatalog::keys(self::RESOURCE, 'read', 'write', 'delete'),
        ];
    }

    public static function requireRead(): string
    {
        return CapabilityCatalog::requirement(self::RESOURCE, CapabilityCatalog::ACTION_READ);
    }

    public static function requireWrite(): string
    {
        return CapabilityCatalog::requirement(self::RESOURCE, CapabilityCatalog::ACTION_WRITE);
    }

    public static function requireDelete(): string
    {
        return CapabilityCatalog::requirement(self::RESOURCE, CapabilityCatalog::ACTION_DELETE);
    }
}
