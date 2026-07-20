<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\DTO;

final readonly class RefreshSessionResult
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
    ) {}
}
