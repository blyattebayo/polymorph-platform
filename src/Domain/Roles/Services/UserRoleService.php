<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Services;

use Polymorph\Platform\Domain\AccessControl\Services\PolicyScopeAuthority;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleAssignmentRepository;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleRepository;
use Polymorph\Platform\Domain\Roles\Core\Exceptions\RoleNotAssignableException;
use Polymorph\Platform\Domain\Users\Core\Models\User;

final readonly class UserRoleService
{
    public function __construct(
        private RoleAssignmentRepository $assignments,
        private RoleRepository $roles,
        private PolicyScopeAuthority $policyScope,
    ) {}

    /** @param list<int> $roleIds */
    public function assertRolesExist(array $roleIds): void
    {
        if ($roleIds !== [] && count($this->roles->codesByIds($roleIds)) !== count($roleIds)) {
            throw new RoleNotAssignableException('One or more roles do not exist.');
        }
    }

    public function assertCanMutate(User $target): void
    {
        foreach ($this->assignments->roleCodesForUser((int) $target->id) as $roleCode) {
            $this->policyScope->assertCanManageRolePolicies($roleCode);
        }
    }

    /** @param list<int> $requestedRoleIds */
    public function assertRoleChangeAllowed(?int $targetUserId, array $requestedRoleIds): void
    {
        $this->assertRolesExist($requestedRoleIds);
        $current = $targetUserId === null ? [] : $this->assignments->roleIdsForUser($targetUserId);
        $changed = array_values(array_unique([
            ...array_diff($requestedRoleIds, $current),
            ...array_diff($current, $requestedRoleIds),
        ]));

        foreach ($this->roles->codesByIds($changed) as $roleCode) {
            $this->policyScope->assertCanManageRolePolicies($roleCode);
        }
    }

    /** @param list<int> $roleIds */
    public function sync(int $userId, array $roleIds): void
    {
        $this->assignments->syncForUser($userId, $roleIds);
    }
}
