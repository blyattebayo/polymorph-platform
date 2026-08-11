<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Contracts;

use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;

interface AuthenticationLock
{
    /** Serialize authentication state changes for one user in the current transaction. */
    public function forUser(UserId $userId): void;
}
