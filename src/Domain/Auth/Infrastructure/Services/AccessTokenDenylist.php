<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Мгновенный отзыв access-токенов по claim `sid`.
 *
 * Access-токен живёт access_ttl, поэтому при отзыве сессии её id достаточно
 * подержать в кэше чуть дольше access_ttl — после этого токен мёртв сам по себе.
 */
final class AccessTokenDenylist
{
    /**
     * @param list<int> $sessionIds
     */
    public function revoke(array $sessionIds): void
    {
        $ttl = $this->ttlSeconds();

        foreach ($sessionIds as $sessionId) {
            Cache::put($this->key((int) $sessionId), true, $ttl);
        }
    }

    public function isRevoked(int $sessionId): bool
    {
        return Cache::get($this->key($sessionId)) === true;
    }

    private function key(int $sessionId): string
    {
        return 'auth:revoked_session:'.$sessionId;
    }

    private function ttlSeconds(): int
    {
        return (int) config('jwt.access_ttl', 900) + (int) config('jwt.leeway', 5) + 60;
    }
}
