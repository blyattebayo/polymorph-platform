<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Domain\ValueObjects;

use Polymorph\Platform\Domain\Auth\Domain\Exceptions\AuthInvariantViolation;

final readonly class SessionId
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function __construct(public string $value)
    {
        if (preg_match(self::UUID, $value) !== 1) {
            throw new AuthInvariantViolation('Session id must be a valid UUID.');
        }
    }

    public function equals(self $other): bool
    {
        return strtolower($this->value) === strtolower($other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
