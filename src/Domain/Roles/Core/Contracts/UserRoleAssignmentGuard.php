<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Core\Contracts;

use Polymorph\Platform\Domain\Roles\Core\Exceptions\RoleNotAssignableException;

interface UserRoleAssignmentGuard
{
    /**
     * @param  list<int>  $roleIds
     *
     * @throws RoleNotAssignableException
     */
    public function assertAssignable(array $roleIds): void;
}
