<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken;

final readonly class PersonalAccessTokenId
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public string $value;

    public function __construct(string $value)
    {
        if (preg_match(self::UUID, $value) !== 1) {
            throw new PersonalAccessTokenInvariantViolation(
                PersonalAccessTokenInvariant::InvalidId,
                'Personal access token id must be a valid UUID.',
            );
        }

        $this->value = strtolower($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
