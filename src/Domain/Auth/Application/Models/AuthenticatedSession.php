<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Models;

use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\SessionId;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

final readonly class AuthenticatedSession
{
    public function __construct(
        public SessionId $sessionId,
        public UserIdentity $user,
    ) {}
}
