<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Access;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * Access-манифест домена: ресурсы, определения capability и дефолтные роли —
 * в одном файле (раньше пара Capabilities + CapabilityProvider).
 *
 * Два ресурса, потому что это два разных полномочия: править политики и
 * раздавать их субъектам. Было: 'policy.manage'/'policy.assign' с action 'access'.
 */
final class AccessControlCapabilities implements CapabilityDefinitionProvider
{
    public const POLICY = 'acl.policy';

    public const ASSIGNMENT = 'acl.assignment';

    public function capabilities(): array
    {
        return [
            new CapabilityDefinition(self::POLICY, CapabilityCatalog::ACTION_MANAGE, 'Manage policies'),
            new CapabilityDefinition(self::ASSIGNMENT, CapabilityCatalog::ACTION_MANAGE, 'Assign policies'),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_ACCESS_POLICY_MANAGER => [
                CapabilityCatalog::capabilityKey(self::POLICY, CapabilityCatalog::ACTION_MANAGE),
                CapabilityCatalog::capabilityKey(self::ASSIGNMENT, CapabilityCatalog::ACTION_MANAGE),
            ],
        ];
    }
}
