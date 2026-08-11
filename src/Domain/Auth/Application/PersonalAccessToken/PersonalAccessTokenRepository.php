<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken;

use DateTimeImmutable;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenDigest;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenId;

interface PersonalAccessTokenRepository
{
    public function add(PersonalAccessToken $token): void;

    public function findByDigest(PersonalAccessTokenDigest $digest): ?PersonalAccessToken;

    /** Must lock the row for the current transaction. */
    public function findByIdForUpdate(PersonalAccessTokenId $id): ?PersonalAccessToken;

    public function save(PersonalAccessToken $token): void;

    public function recordSuccessfulUse(PersonalAccessTokenId $id, DateTimeImmutable $usedAt): void;
}
