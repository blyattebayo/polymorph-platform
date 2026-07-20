<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Services;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleAssignmentRepository;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleRepository;
use Polymorph\Platform\Domain\Users\Core\Contracts\PrivilegedUserMembership;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Illuminate\Support\Facades\DB;

final class AccessProvisioner
{
    public function __construct(
        private readonly RoleAssignmentRepository $roleAssignments,
        private readonly RoleRepository $roles,
        private readonly PrivilegedUserMembership $privilegedUserMembership,
    ) {
    }

    public function syncForUser(User $user): void
    {
        // Атомарно: провижининг baseline-ролей (возможное создание ролей +
        // assign/unassign) либо применяется целиком, либо откатывается.
        DB::transaction(function () use ($user): void {
            if ($this->privilegedUserMembership->isSystemAdministrator($user->userId())) {
                $this->revokeBaselineRoles($user->userId());

                return;
            }

            $baselineRoleIds = $this->requireBaselineRoleIds();
            if ($baselineRoleIds === []) {
                throw new \RuntimeException('Unable to provision baseline roles.');
            }

            foreach ($baselineRoleIds as $roleId) {
                $this->roleAssignments->assign($user->userId(), $roleId);
            }
        });
    }

    private function revokeBaselineRoles(int $userId): void
    {
        foreach (BuiltInRoleCatalog::baselineRoleCodes() as $roleCode) {
            $this->revokeRole($userId, $roleCode);
        }
    }

    private function revokeRole(int $userId, string $roleCode): void
    {
        $roleId = $this->roleIdByCode($roleCode);
        if ($roleId <= 0) {
            return;
        }

        $this->roleAssignments->unassign($userId, $roleId);
    }

    private function roleIdByCode(string $roleCode): int
    {
        return (int) ($this->roles->findByCode($roleCode)?->id ?? 0);
    }

    /**
     * @return list<int>
     */
    private function requireBaselineRoleIds(): array
    {
        $ids = [];
        foreach (BuiltInRoleCatalog::baselineRoleCodes() as $roleCode) {
            $ids[] = $this->requireBuiltInRoleId($roleCode);
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    private function requireBuiltInRoleId(string $roleCode): int
    {
        $roleId = $this->roleIdByCode($roleCode);
        if ($roleId > 0) {
            return $roleId;
        }

        $definition = collect(BuiltInRoleCatalog::definitions())
            ->first(static fn (array $role): bool => $role['code'] === $roleCode);
        if (! is_array($definition)) {
            throw new \RuntimeException("Unable to provision built-in role {$roleCode}.");
        }

        $role = $this->roles->ensureByCode(
            (string) $definition['code'],
            (string) $definition['name'],
            $definition['description'] ?? null,
        );

        return (int) ($role->id ?? 0);
    }
}