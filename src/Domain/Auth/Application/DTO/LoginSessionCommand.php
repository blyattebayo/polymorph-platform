<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\DTO;

final readonly class LoginSessionCommand
{
    public function __construct(
        public string $email,
        public string $password,
        public ?string $ip,
        public ?string $userAgent,
    ) {}
}
