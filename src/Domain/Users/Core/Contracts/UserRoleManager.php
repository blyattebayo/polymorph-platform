<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Contracts;

use Polymorph\Platform\Domain\Users\Core\Exceptions\RoleAssignmentRejectedException;
use Polymorph\Platform\Domain\Users\Core\Exceptions\SystemAdminPrivilegeRequiredException;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

interface UserRoleManager
{
    /**
     * @param  list<int>  $roleIds
     *
     * @throws RoleAssignmentRejectedException
     */
    public function assertAssignable(array $roleIds): void;

    /**
     * Меняет ли запрошенный набор ролей членство цели в system.admin — и если
     * да, вправе ли актор это делать. `$targetUserId === null` — создание
     * нового пользователя (текущее членство пусто).
     *
     * @param  list<int>  $requestedRoleIds
     *
     * @throws SystemAdminPrivilegeRequiredException
     */
    public function assertRoleChangeAllowed(UserIdentity $actor, ?int $targetUserId, array $requestedRoleIds): void;

    /**
     * @param  list<int>  $roleIds
     */
    public function syncForUser(int $userId, array $roleIds): void;
}
