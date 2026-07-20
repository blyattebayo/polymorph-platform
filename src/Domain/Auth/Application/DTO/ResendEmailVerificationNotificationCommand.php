<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\DTO;

final readonly class ResendEmailVerificationNotificationCommand
{
    public function __construct(
        public int $userId,
    ) {}
}
