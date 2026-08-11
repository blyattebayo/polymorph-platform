<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken;

final readonly class PersonalAccessTokenName
{
    public const MAX_LENGTH = 255;

    public string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);

        if ($normalized === '' || mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new PersonalAccessTokenInvariantViolation(
                PersonalAccessTokenInvariant::InvalidName,
                'Personal access token name must contain between 1 and 255 characters.',
            );
        }

        $this->value = $normalized;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
