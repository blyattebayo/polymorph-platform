<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Models;

use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\SessionId;
use Polymorph\Platform\Domain\Users\Core\Models\User;

final readonly class AuthenticatedSession
{
    public function __construct(
        public SessionId $sessionId,
        public User $user,
    ) {}
}
