<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\DTO;

final readonly class CreatePersonalAccessTokenCommand
{
    public function __construct(
        public int $userId,
        public string $name,
        public int $createdByUserId,
        public ?string $ttl = null,
    ) {}
}
