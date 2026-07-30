<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Access;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Access\CapabilitySet;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * Access-манифест домена: ресурс, определения capability, дефолтные роли и
 * route-требования — в одном файле.
 */
final class UsersCapabilities implements CapabilityDefinitionProvider
{
    public const RESOURCE = 'user';

    public function capabilities(): array
    {
        return CapabilitySet::for(self::RESOURCE)
            ->read('Read users')
            ->manage('Manage users')
            ->all();
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_USERS_MANAGER => CapabilityCatalog::keys(self::RESOURCE, 'read', 'manage'),
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
