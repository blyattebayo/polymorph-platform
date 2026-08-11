<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Models;

use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

final readonly class AuthUser
{
    public function __construct(
        public UserIdentity $identity,
        public string $passwordHash,
    ) {}

    public function id(): int
    {
        return $this->identity->userId();
    }
}
