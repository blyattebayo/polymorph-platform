<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Persistence;

use Polymorph\Platform\Domain\Auth\Application\DTO\RefreshSessionRotation;
use Polymorph\Platform\Domain\Auth\Application\Exceptions\AuthSessionUnauthorizedException;
use Polymorph\Platform\Domain\Auth\Application\Policies\SessionRotationDecision;
use Polymorph\Platform\Domain\Auth\Application\Policies\SessionRotationPolicy;
use Polymorph\Platform\Domain\Auth\Core\Contracts\RefreshSessionRepository;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\AccessTokenDenylist;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserSessionRevoker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentRefreshSessionRepository implements RefreshSessionRepository, UserSessionRevoker
{
    public function __construct(
        private readonly SessionRotationPolicy $rotationPolicy,
        private readonly AccessTokenDenylist $denylist,
    ) {}

    public function createForUser(int $userId, ?string $ip = null, ?string $userAgent = null): RefreshSessionRotation
    {
        $token = $this->newPlainToken();
        $absoluteExpiresAt = CarbonImmutable::now('UTC')->addSeconds($this->familyTtl());

        $outerTransactionLevel = DB::transactionLevel();
        DB::beginTransaction();

        try {
            $this->evictSessionsOverLimit($userId);

            $sessionId = (int) DB::table('auth_sessions')->insertGetId([
                'user_id' => $userId,
                'token_hash' => $this->hashToken($token),
                'family_id' => (string) Str::uuid(),
                'parent_id' => null,
                'last_used_at' => now(),
                'rotated_at' => null,
                'expires_at' => $this->expiresAt($absoluteExpiresAt),
                'absolute_expires_at' => $absoluteExpiresAt,
                'revoked_at' => null,
                'ip' => $ip,
                'user_agent' => $userAgent,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return new RefreshSessionRotation($sessionId, $userId, $token, $absoluteExpiresAt->getTimestamp());
        } catch (\Throwable $exception) {
            // Откатываем только собственную транзакцию: после commit уровень
            // уже вернулся к внешнему, и rollback снёс бы чужую транзакцию
            // (например, обёртку RefreshDatabase в тестах).
            if (DB::transactionLevel() > $outerTransactionLevel) {
                DB::rollBack();
            }

            throw $exception;
        }
    }

    public function rotate(string $refreshToken, ?string $ip = null, ?string $userAgent = null): RefreshSessionRotation
    {
        if ($refreshToken === '') {
            throw new AuthSessionUnauthorizedException('Missing refresh token.');
        }

        $outerTransactionLevel = DB::transactionLevel();
        DB::beginTransaction();

        try {
            $session = DB::table('auth_sessions')
                ->where('token_hash', $this->hashToken($refreshToken))
                ->lockForUpdate()
                ->first();

            $decision = $this->rotationPolicy->decide($session);

            if ($decision === SessionRotationDecision::Missing) {
                DB::rollBack();
                throw new AuthSessionUnauthorizedException('Invalid or expired refresh token.');
            }

            if ($decision === SessionRotationDecision::Reused) {
                $this->revokeFamily((string) $session->family_id);
                DB::commit();
                throw new AuthSessionUnauthorizedException('Invalid or expired refresh token.');
            }

            if ($decision === SessionRotationDecision::Expired) {
                DB::table('auth_sessions')->where('id', (int) $session->id)->update([
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::commit();
                throw new AuthSessionUnauthorizedException('Invalid or expired refresh token.');
            }

            DB::table('auth_sessions')->where('id', (int) $session->id)->update([
                'last_used_at' => now(),
                'rotated_at' => now(),
                'updated_at' => now(),
            ]);

            $absoluteExpiresAt = $this->parseNullableTimestamp($session->absolute_expires_at ?? null);

            $newToken = $this->newPlainToken();
            $newSessionId = (int) DB::table('auth_sessions')->insertGetId([
                'user_id' => (int) $session->user_id,
                'token_hash' => $this->hashToken($newToken),
                'family_id' => (string) $session->family_id,
                'parent_id' => (int) $session->id,
                'last_used_at' => now(),
                'rotated_at' => null,
                'expires_at' => $this->expiresAt($absoluteExpiresAt),
                'absolute_expires_at' => $absoluteExpiresAt,
                'revoked_at' => null,
                'ip' => $ip,
                'user_agent' => $userAgent,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            $absoluteExpiresAtEpoch = ($absoluteExpiresAt ?? CarbonImmutable::now('UTC')->addSeconds($this->familyTtl()))
                ->getTimestamp();

            return new RefreshSessionRotation($newSessionId, (int) $session->user_id, $newToken, $absoluteExpiresAtEpoch);
        } catch (\Throwable $exception) {
            // См. комментарий в createForUser: rollback только своей транзакции.
            if (DB::transactionLevel() > $outerTransactionLevel) {
                DB::rollBack();
            }

            throw $exception;
        }
    }

    public function revokeByRefreshToken(string $refreshToken): void
    {
        if ($refreshToken === '') {
            return;
        }

        $session = DB::table('auth_sessions')
            ->where('token_hash', $this->hashToken($refreshToken))
            ->first(['family_id']);

        if ($session === null) {
            return;
        }

        $this->revokeFamily((string) $session->family_id);
    }

    public function revokeAllForUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $this->denylistRecentSessionIds(
            DB::table('auth_sessions')
                ->where('user_id', $userId)
                ->whereNull('revoked_at'),
        );

        DB::table('auth_sessions')
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function revokeForUser(int $userId, int $sessionId): bool
    {
        $session = DB::table('auth_sessions')
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->first(['family_id']);

        if ($session === null) {
            return false;
        }

        $hadActiveRows = DB::table('auth_sessions')
            ->where('family_id', (string) $session->family_id)
            ->whereNull('revoked_at')
            ->exists();

        if (! $hadActiveRows) {
            return false;
        }

        $this->revokeFamily((string) $session->family_id);

        return true;
    }

    public function activeForUser(int $userId): array
    {
        return DB::table('auth_sessions')
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->whereNull('rotated_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    public function familyIdOf(int $sessionId): ?string
    {
        $familyId = DB::table('auth_sessions')
            ->where('id', $sessionId)
            ->value('family_id');

        return is_string($familyId) && $familyId !== '' ? $familyId : null;
    }

    public function pruneExpired(): int
    {
        return DB::table('auth_sessions')
            ->where('expires_at', '<', now()->subDays(7))
            ->delete();
    }

    /**
     * Отзывает старейшие активные сессии, чтобы после создания новой
     * их количество не превышало настроенный лимит.
     */
    private function evictSessionsOverLimit(int $userId): void
    {
        $max = max(1, (int) config('jwt.max_sessions_per_user', 20));

        $active = DB::table('auth_sessions')
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->whereNull('rotated_at')
            ->where('expires_at', '>', now())
            ->orderBy('created_at')
            ->orderBy('id') // детерминированный tiebreak: при равном created_at старше тот, у кого меньше id
            ->lockForUpdate()
            ->get(['id', 'family_id']);

        $excess = count($active) - $max + 1;
        if ($excess <= 0) {
            return;
        }

        foreach (array_slice($active->all(), 0, $excess) as $session) {
            $this->revokeFamily((string) $session->family_id);
        }
    }

    private function revokeFamily(string $familyId): void
    {
        $this->denylistRecentSessionIds(
            DB::table('auth_sessions')
                ->where('family_id', $familyId)
                ->whereNull('revoked_at'),
        );

        DB::table('auth_sessions')
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Кладёт в denylist id отзываемых сессий, на которые ещё может ссылаться
     * живой access-токен. Любой непросроченный access выпущен не раньше, чем
     * access_ttl назад, а его sid — это строка, созданная в момент выпуска,
     * поэтому достаточно строк с created_at внутри этого окна.
     */
    private function denylistRecentSessionIds(Builder $query): void
    {
        $cutoff = CarbonImmutable::now('UTC')->subSeconds(
            (int) config('jwt.access_ttl', 900) + (int) config('jwt.leeway', 5) + 60,
        );

        $sessionIds = (clone $query)
            ->where('created_at', '>=', $cutoff)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($sessionIds !== []) {
            $this->denylist->revoke($sessionIds);
        }
    }

    private function newPlainToken(): string
    {
        return 'pmph_rt_'.Str::random(96);
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function expiresAt(?CarbonImmutable $absoluteExpiresAt): CarbonImmutable
    {
        $expiresAt = CarbonImmutable::now('UTC')->addSeconds((int) config('jwt.refresh_ttl', 2592000));

        if ($absoluteExpiresAt !== null && $absoluteExpiresAt->lessThan($expiresAt)) {
            return $absoluteExpiresAt;
        }

        return $expiresAt;
    }

    private function familyTtl(): int
    {
        return (int) config('jwt.refresh_family_ttl', 90 * 24 * 60 * 60);
    }

    private function parseNullableTimestamp(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value);
    }
}
