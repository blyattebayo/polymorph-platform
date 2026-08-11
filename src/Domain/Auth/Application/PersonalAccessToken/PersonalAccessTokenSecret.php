<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken;

use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenDigest;

final readonly class PersonalAccessTokenSecret
{
    public function __construct(
        private string $plaintext,
        public PersonalAccessTokenDigest $digest,
        public string $displayHint,
    ) {}

    public function reveal(): string
    {
        return $this->plaintext;
    }

    /** @return array{plaintext: string, display_hint: string} */
    public function __debugInfo(): array
    {
        return [
            'plaintext' => '[redacted]',
            'display_hint' => $this->displayHint,
        ];
    }
}
