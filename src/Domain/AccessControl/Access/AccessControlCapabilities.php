<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Access;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * Access-манифест домена: ресурсы, определения capability, дефолтные роли и
 * route-требования — в одном файле.
 *
 * Два ресурса, потому что это два разных полномочия: править политики и
 * раздавать их субъектам.
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
                ...CapabilityCatalog::keys(self::POLICY, 'manage'),
                ...CapabilityCatalog::keys(self::ASSIGNMENT, 'manage'),
            ],
        ];
    }

    public static function requirePolicyManage(): string
    {
        return CapabilityCatalog::requirement(self::POLICY, CapabilityCatalog::ACTION_MANAGE);
    }

    public static function requireAssignmentManage(): string
    {
        return CapabilityCatalog::requirement(self::ASSIGNMENT, CapabilityCatalog::ACTION_MANAGE);
    }
}
