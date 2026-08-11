<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\PersonalAccessToken\Persistence;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenRepository;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenDigest;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenId;

final readonly class EloquentPersonalAccessTokenRepository implements PersonalAccessTokenRepository
{
    private const MINIMUM_USAGE_WRITE_INTERVAL_SECONDS = 60;

    public function __construct(private PersonalAccessTokenMapper $mapper) {}

    public function add(PersonalAccessToken $token): void
    {
        PersonalAccessTokenRecord::query()->create($this->mapper->toPersistence($token));
    }

    public function findByDigest(PersonalAccessTokenDigest $digest): ?PersonalAccessToken
    {
        $record = PersonalAccessTokenRecord::query()
            ->where('secret_digest', $digest->value)
            ->first();

        return $record instanceof PersonalAccessTokenRecord
            ? $this->mapper->toDomain($record)
            : null;
    }

    public function findByIdForUpdate(PersonalAccessTokenId $id): ?PersonalAccessToken
    {
        $record = PersonalAccessTokenRecord::query()
            ->whereKey($id->value)
            ->lockForUpdate()
            ->first();

        return $record instanceof PersonalAccessTokenRecord
            ? $this->mapper->toDomain($record)
            : null;
    }

    public function save(PersonalAccessToken $token): void
    {
        PersonalAccessTokenRecord::query()
            ->whereKey($token->id()->value)
            ->update($this->mapper->revocationToPersistence($token->revocation()));
    }

    public function recordSuccessfulUse(PersonalAccessTokenId $id, DateTimeImmutable $usedAt): void
    {
        $threshold = $usedAt->modify('-'.self::MINIMUM_USAGE_WRITE_INTERVAL_SECONDS.' seconds');

        DB::table(PersonalAccessTokenRecord::TABLE)
            ->where('id', $id->value)
            ->whereNull('revoked_at')
            ->where(function ($query) use ($threshold): void {
                $query->whereNull('last_used_at')->orWhere('last_used_at', '<=', $threshold->format('Y-m-d H:i:s.uP'));
            })
            ->update(['last_used_at' => $usedAt->format('Y-m-d H:i:s.uP')]);
    }
}
