<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Access;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * Access-манифест домена: ресурс, определения capability и дефолтные роли —
 * в одном файле (раньше пара Capabilities + CapabilityProvider).
 *
 * Было: 'user.read'/'user.lifecycle' с action 'access' (действие в имени ресурса).
 */
final class UsersCapabilities implements CapabilityDefinitionProvider
{
    public const RESOURCE = 'user';

    public function capabilities(): array
    {
        return [
            new CapabilityDefinition(self::RESOURCE, CapabilityCatalog::ACTION_READ, 'Read users'),
            new CapabilityDefinition(self::RESOURCE, CapabilityCatalog::ACTION_MANAGE, 'Manage users'),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_USERS_MANAGER => [
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_READ),
                CapabilityCatalog::capabilityKey(self::RESOURCE, CapabilityCatalog::ACTION_MANAGE),
            ],
        ];
    }
}
