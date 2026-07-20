<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Contracts;

use Polymorph\Platform\Domain\Users\Core\Exceptions\RoleAssignmentRejectedException;

interface UserRoleManager
{
    /**
     * @param list<int> $roleIds
     *
     * @throws RoleAssignmentRejectedException
     */
    public function assertAssignable(array $roleIds): void;

    /**
     * @param list<int> $roleIds
     */
    public function syncForUser(int $userId, array $roleIds): void;
}
