<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\Data;

use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenSecret;

final readonly class IssuedPersonalAccessToken
{
    public function __construct(
        public PersonalAccessTokenView $token,
        private PersonalAccessTokenSecret $secret,
    ) {}

    public function revealPlaintext(): string
    {
        return $this->secret->reveal();
    }

    /** @return array{token: PersonalAccessTokenView, plaintext: string} */
    public function __debugInfo(): array
    {
        return [
            'token' => $this->token,
            'plaintext' => '[redacted]',
        ];
    }
}
