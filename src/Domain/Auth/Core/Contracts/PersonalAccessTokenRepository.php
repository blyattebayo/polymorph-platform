<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\Contracts;

use Polymorph\Platform\Domain\Auth\Core\Models\PersonalAccessToken;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PersonalAccessTokenRepository
{
    public function create(
        int $userId,
        string $name,
        int $createdByUserId,
        string $tokenHash,
        string $tokenPrefix,
        ?\DateTimeInterface $expiresAt = null,
    ): PersonalAccessToken;

    public function findByHash(string $tokenHash): ?PersonalAccessToken;

    public function revoke(int $tokenId): bool;

    public function revokeForUser(int $tokenId, int $userId): bool;

    /**
     * @return list<PersonalAccessToken>
     */
    public function listForUser(int $userId): array;

    /**
     * @param array{user_id?: int|null, status?: string|null} $filters
     */
    public function paginateAll(int $page, int $perPage, array $filters = []): LengthAwarePaginator;

    public function touchLastUsedThrottled(int $tokenId): void;
}
