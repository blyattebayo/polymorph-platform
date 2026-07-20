<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Services;

use Polymorph\Platform\Domain\Users\Core\Contracts\PrivilegedUserMembership;
use Polymorph\Platform\Domain\Users\Core\Contracts\SystemAdministratorGuard;
use Polymorph\Platform\Domain\Users\Core\Exceptions\SystemAdministratorMutationException;
use Polymorph\Platform\Domain\Users\Core\Models\User;

final class RoleBasedSystemAdministratorGuard implements SystemAdministratorGuard
{
    public function __construct(
        private readonly PrivilegedUserMembership $privilegedUserMembership,
    ) {
    }

    public function assertCanMutate(User $user): void
    {
        if ($this->privilegedUserMembership->isSystemAdministrator((int) $user->id)) {
            throw SystemAdministratorMutationException::forUser((int) $user->id);
        }
    }

}
