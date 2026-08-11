<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Models;

use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\SessionId;

final readonly class IssuedSession
{
    public function __construct(
        public AuthUser $user,
        public SessionId $sessionId,
        public string $credential,
    ) {}
}
