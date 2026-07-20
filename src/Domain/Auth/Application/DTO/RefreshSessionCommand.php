<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\DTO;

final readonly class RefreshSessionCommand
{
    public function __construct(
        public string $refreshToken,
        public ?string $ip = null,
        public ?string $userAgent = null,
    ) {
    }
}
