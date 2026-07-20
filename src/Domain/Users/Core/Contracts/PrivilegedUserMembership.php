<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Contracts;

interface PrivilegedUserMembership
{
    public function isSystemAdministrator(int $userId): bool;
}
