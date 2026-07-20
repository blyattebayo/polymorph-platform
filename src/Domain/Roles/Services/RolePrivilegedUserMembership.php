<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Services;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleAssignmentRepository;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleRepository;
use Polymorph\Platform\Domain\Roles\Core\Models\Role;
use Polymorph\Platform\Domain\Users\Core\Contracts\PrivilegedUserMembership;

final class RolePrivilegedUserMembership implements PrivilegedUserMembership
{
    private ?int $adminRoleId = null;

    public function __construct(
        private readonly RoleRepository $roles,
        private readonly RoleAssignmentRepository $roleAssignments,
    ) {}

    public function isSystemAdministrator(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $adminRoleId = $this->resolveAdminRoleId();
        if ($adminRoleId <= 0) {
            return false;
        }

        return in_array($adminRoleId, $this->roleAssignments->roleIdsForUser($userId), true);
    }

    private function resolveAdminRoleId(): int
    {
        if ($this->adminRoleId !== null) {
            return $this->adminRoleId;
        }

        $adminRole = $this->roles->findByCode(BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN);
        $this->adminRoleId = $adminRole instanceof Role ? (int) $adminRole->id : 0;

        return $this->adminRoleId;
    }
}
