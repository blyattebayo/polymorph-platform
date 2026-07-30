<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Services;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
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
     * Правило проверяется по ДИФФУ членства в system.admin, а не по составу
     * запроса: обычный users.manager может пересобирать любые роли пользователя,
     * пока не трогает админскую. До этого guard'а выдать system.admin мог любой
     * носитель user.lifecycle — эскалация привилегий одним запросом.
     */
    public function assertRoleChangeAllowed(UserIdentity $actor, ?int $targetUserId, array $requestedRoleIds): void
    {
        $adminRole = $this->roles->findByCode(BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN);
        if (! $adminRole instanceof Role) {
            return;
        }

        $adminRoleId = (int) $adminRole->id;

        $requestedHasAdmin = in_array(
            $adminRoleId,
            array_map(static fn (mixed $id): int => (int) $id, $requestedRoleIds),
            true,
        );

        $targetHasAdmin = $targetUserId !== null
            && in_array($adminRoleId, $this->roleAssignments->roleIdsForUser($targetUserId), true);

        if ($requestedHasAdmin === $targetHasAdmin) {
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
