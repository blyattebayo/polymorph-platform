<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\DTO;

final readonly class RefreshSessionRotation
{
    public function __construct(
        public int $sessionId,
        public int $userId,
        public string $refreshToken,
        /** Абсолютный потолок жизни семьи сессий (Unix-время, сек). */
        public int $absoluteExpiresAt = 0,
    ) {
    }
}
