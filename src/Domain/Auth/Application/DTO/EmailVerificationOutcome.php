<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\DTO;

enum EmailVerificationOutcome: string
{
    case Verified = 'verified';
    case AlreadyVerified = 'already';
    case Error = 'error';
}
