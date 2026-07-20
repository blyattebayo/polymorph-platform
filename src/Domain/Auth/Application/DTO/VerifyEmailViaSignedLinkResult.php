<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\DTO;

final readonly class VerifyEmailViaSignedLinkResult
{
    public function __construct(
        public EmailVerificationOutcome $outcome,
    ) {}
}
