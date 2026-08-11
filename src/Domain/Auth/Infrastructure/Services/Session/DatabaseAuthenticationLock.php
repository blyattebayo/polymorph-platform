<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services\Session;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Auth\Application\Contracts\AuthenticationLock;
use Polymorph\Platform\Domain\Auth\Domain\Exceptions\AuthInvariantViolation;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;

final class DatabaseAuthenticationLock implements AuthenticationLock
{
    public function forUser(UserId $userId): void
    {
        $locked = DB::table('users')
            ->where('id', $userId->value)
            ->lockForUpdate()
            ->value('id');

        if ((int) $locked !== $userId->value) {
            throw new AuthInvariantViolation('Cannot lock authentication state for a missing user.');
        }
    }
}
