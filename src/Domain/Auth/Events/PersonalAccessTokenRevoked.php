<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Events;

final readonly class PersonalAccessTokenRevoked
{
    public function __construct(
        public int $tokenId,
        public ?int $userId,
    ) {}
}
