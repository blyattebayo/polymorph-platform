<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services;

use Illuminate\Support\Facades\Cache;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\JwtConfig;

/**
 * Мгновенный отзыв access-токенов по claim `sid`.
 *
 * Access-токен живёт access_ttl, поэтому при отзыве сессии её id достаточно
 * подержать в кэше чуть дольше access_ttl — после этого токен мёртв сам по себе.
 */
final class AccessTokenDenylist
{
    public function __construct(
        private readonly JwtConfig $jwt,
    ) {}

    /**
     * @param  list<int>  $sessionIds
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

    /** Тот же запас, что и у отсечки в EloquentRefreshSessionRepository. */
    public function ttlSeconds(): int
    {
        return $this->jwt->accessTtl + $this->jwt->leeway + 60;
    }

    private function key(int $sessionId): string
    {
        return 'auth:revoked_session:'.$sessionId;
    }
}
