<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Services;

use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleAssignmentRepository;
use Polymorph\Platform\Domain\Roles\Core\Contracts\UserRoleAssignmentGuard;
use Polymorph\Platform\Domain\Roles\Core\Exceptions\RoleNotAssignableException;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserRoleManager;
use Polymorph\Platform\Domain\Users\Core\Exceptions\RoleAssignmentRejectedException;

final class RoleAssignmentUserRoleManager implements UserRoleManager
{
    public function __construct(
        private readonly UserRoleAssignmentGuard $assignmentGuard,
        private readonly RoleAssignmentRepository $roleAssignments,
    ) {
    }

    public function assertAssignable(array $roleIds): void
    {
        try {
            $this->assignmentGuard->assertAssignable($roleIds);
        } catch (RoleNotAssignableException $exception) {
            throw new RoleAssignmentRejectedException($exception->getMessage(), 0, $exception);
        }
    }

    public function syncForUser(int $userId, array $roleIds): void
    {
        $this->roleAssignments->syncForUser($userId, $roleIds);
    }
}
