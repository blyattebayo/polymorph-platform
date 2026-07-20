<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\Contracts;

use Polymorph\Platform\Domain\Auth\Application\DTO\RefreshSessionRotation;

interface RefreshSessionRepository
{
    public function createForUser(int $userId, ?string $ip = null, ?string $userAgent = null): RefreshSessionRotation;

    public function rotate(string $refreshToken, ?string $ip = null, ?string $userAgent = null): RefreshSessionRotation;

    public function revokeByRefreshToken(string $refreshToken): void;

    public function revokeAllForUser(int $userId): void;

    public function revokeForUser(int $userId, int $sessionId): bool;

    /**
     * @return list<object>
     */
    public function activeForUser(int $userId): array;

    public function familyIdOf(int $sessionId): ?string;

    public function pruneExpired(): int;
}
