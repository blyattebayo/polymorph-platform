<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Auth\Core\Contracts\PersonalAccessTokenRepository;
use Polymorph\Platform\Domain\Auth\Core\Models\PersonalAccessToken;

final class EloquentPersonalAccessTokenRepository implements PersonalAccessTokenRepository
{
    public function create(
        int $userId,
        string $name,
        int $createdByUserId,
        string $tokenHash,
        string $tokenPrefix,
        ?\DateTimeInterface $expiresAt = null,
    ): PersonalAccessToken {
        /** @var PersonalAccessToken $token */
        $token = PersonalAccessToken::query()->create([
            'user_id' => $userId,
            'name' => $name,
            'token_hash' => $tokenHash,
            'token_prefix' => $tokenPrefix,
            'expires_at' => $expiresAt,
            'created_by_user_id' => $createdByUserId,
        ]);

        return $token;
    }

    public function findByHash(string $tokenHash): ?PersonalAccessToken
    {
        /** @var PersonalAccessToken|null $token */
        $token = PersonalAccessToken::query()->where('token_hash', $tokenHash)->first();

        return $token;
    }

    public function revoke(int $tokenId): bool
    {
        return PersonalAccessToken::query()
            ->whereKey($tokenId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]) > 0;
    }

    public function revokeForUser(int $tokenId, int $userId): bool
    {
        return PersonalAccessToken::query()
            ->whereKey($tokenId)
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]) > 0;
    }

    public function listForUser(int $userId): array
    {
        return PersonalAccessToken::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    public function paginateAll(int $page, int $perPage, array $filters = []): LengthAwarePaginator
    {
        $query = PersonalAccessToken::query()->orderByDesc('id');

        if (isset($filters['user_id']) && is_int($filters['user_id']) && $filters['user_id'] > 0) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['status']) && is_string($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->whereNull('revoked_at')
                    ->where(function ($builder): void {
                        $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            } elseif ($filters['status'] === 'revoked') {
                $query->whereNotNull('revoked_at');
            } elseif ($filters['status'] === 'expired') {
                $query->whereNull('revoked_at')->whereNotNull('expires_at')->where('expires_at', '<=', now());
            }
        }

        return $query->paginate(perPage: $perPage, page: $page);
    }

    public function touchLastUsedThrottled(int $tokenId): void
    {
        $threshold = now()->subMinute();

        DB::table('auth_personal_access_tokens')
            ->where('id', $tokenId)
            ->where(function ($query) use ($threshold): void {
                $query->whereNull('last_used_at')->orWhere('last_used_at', '<', $threshold);
            })
            ->update([
                'last_used_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
