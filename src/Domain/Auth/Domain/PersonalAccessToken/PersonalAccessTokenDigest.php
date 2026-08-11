<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken;

final readonly class PersonalAccessTokenDigest
{
    public string $value;

    public function __construct(string $value)
    {
        if (preg_match('/^[a-f0-9]{64}$/i', $value) !== 1) {
            throw new PersonalAccessTokenInvariantViolation(
                PersonalAccessTokenInvariant::InvalidDigest,
                'Personal access token digest must be a SHA-256 hexadecimal value.',
            );
        }

        $this->value = strtolower($value);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }
}
