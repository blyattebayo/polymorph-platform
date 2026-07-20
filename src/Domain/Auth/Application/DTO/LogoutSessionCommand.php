<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\DTO;

use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

final readonly class LogoutSessionCommand
{
    public function __construct(
        public UserIdentity $actor,
        public bool $allDevices,
        public string $refreshToken,
        public ?int $sessionId = null,
    ) {}
}
