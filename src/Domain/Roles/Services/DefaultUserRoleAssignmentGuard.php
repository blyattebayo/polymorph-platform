<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Services;

use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleRepository;
use Polymorph\Platform\Domain\Roles\Core\Contracts\UserRoleAssignmentGuard;
use Polymorph\Platform\Domain\Roles\Core\Exceptions\RoleNotAssignableException;

final class DefaultUserRoleAssignmentGuard implements UserRoleAssignmentGuard
{
    public function __construct(
        private readonly RoleRepository $roles,
    ) {}

    public function assertAssignable(array $roleIds): void
    {
        if ($roleIds === []) {
            return;
        }

        $roleCodes = array_values($this->roles->codesByIds($roleIds));

        if (count($roleCodes) !== count($roleIds)) {
            throw new RoleNotAssignableException('One or more roles do not exist.');
        }
    }
}
