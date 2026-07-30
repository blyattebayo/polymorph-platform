<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Services;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Services\PolicyScopeAuthority;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleAssignmentRepository;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleRepository;
use Polymorph\Platform\Domain\Roles\Core\Contracts\UserRoleAssignmentGuard;
use Polymorph\Platform\Domain\Roles\Core\Exceptions\RoleNotAssignableException;
use Polymorph\Platform\Domain\Roles\Core\Models\Role;
use Polymorph\Platform\Domain\Users\Core\Contracts\PrivilegedUserMembership;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserRoleManager;
use Polymorph\Platform\Domain\Users\Core\Exceptions\RoleAssignmentRejectedException;
use Polymorph\Platform\Domain\Users\Core\Exceptions\SystemAdminPrivilegeRequiredException;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

final class RoleAssignmentUserRoleManager implements UserRoleManager
{
    public function __construct(
        private readonly UserRoleAssignmentGuard $assignmentGuard,
        private readonly RoleAssignmentRepository $roleAssignments,
        private readonly RoleRepository $roles,
        private readonly PrivilegedUserMembership $privilegedUserMembership,
        private readonly PolicyScopeAuthority $policyScope,
    ) {}

    public function assertAssignable(array $roleIds): void
    {
        try {
            $this->assignmentGuard->assertAssignable($roleIds);
        } catch (RoleNotAssignableException $exception) {
            throw new RoleAssignmentRejectedException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Правило проверяется по ДИФФУ ролей, а не по составу запроса: неизменяемая
     * часть набора может быть шире прав актора (её выдал кто-то привилегированнее),
     * и это не повод запрещать актору менять свою часть.
     *
     * Для изменяемых ролей действуют два ограничения. Членство в system.admin
     * меняет только действующий системный админ. Любая другая роль — контейнер
     * политик, поэтому выдать её вправе лишь тот, кто сам покрывает каждую из
     * них: иначе носитель user/manage выдавал себе plugins.manager или
     * access.policy_manager и одним запросом получал права, которых у него нет.
     */
    public function assertRoleChangeAllowed(UserIdentity $actor, ?int $targetUserId, array $requestedRoleIds): void
    {
        $requested = array_values(array_unique(
            array_map(static fn (mixed $id): int => (int) $id, $requestedRoleIds),
        ));
        $current = $targetUserId === null ? [] : $this->roleAssignments->roleIdsForUser($targetUserId);

        $changed = array_values(array_unique([
            ...array_diff($requested, $current),
            ...array_diff($current, $requested),
        ]));

        if ($changed === []) {
            return;
        }

        $this->assertAdminRoleChangeAllowed($actor, $changed);

        foreach ($this->roles->codesByIds($changed) as $roleCode) {
            $this->policyScope->assertCanManageRolePolicies($roleCode);
        }
    }

    /**
     * @param  list<int>  $changedRoleIds
     */
    private function assertAdminRoleChangeAllowed(UserIdentity $actor, array $changedRoleIds): void
    {
        $adminRole = $this->roles->findByCode(BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN);
        if (! $adminRole instanceof Role) {
            return;
        }

        if (! in_array((int) $adminRole->id, $changedRoleIds, true)) {
            return;
        }

        if (! $this->privilegedUserMembership->isSystemAdministrator($actor->userId())) {
            throw SystemAdminPrivilegeRequiredException::toChangeAdminRole();
        }
    }

    public function syncForUser(int $userId, array $roleIds): void
    {
        $this->roleAssignments->syncForUser($userId, $roleIds);
    }
}
