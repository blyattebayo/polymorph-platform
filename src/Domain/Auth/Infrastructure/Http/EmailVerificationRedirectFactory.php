<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Http;

use Polymorph\Platform\Domain\Auth\Application\DTO\EmailVerificationOutcome;

final readonly class EmailVerificationRedirectFactory
{
    public function withOutcome(EmailVerificationOutcome $outcome): string
    {
        $url = (string) config('auth.email_verification.redirect_to', '/');
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'verify=' . $outcome->value;
    }
}
