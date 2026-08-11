<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken;

use DateTimeImmutable;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;

final readonly class PersonalAccessTokenRevocation
{
    public function __construct(
        public DateTimeImmutable $at,
        public UserId $byUserId,
        public PersonalAccessTokenRevocationReason $reason,
    ) {}
}
