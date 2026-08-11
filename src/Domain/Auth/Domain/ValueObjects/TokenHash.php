<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Domain\ValueObjects;

use Polymorph\Platform\Domain\Auth\Domain\Exceptions\AuthInvariantViolation;

final readonly class TokenHash
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[a-f0-9]{64}$/i', $value) !== 1) {
            throw new AuthInvariantViolation('Token hash must be a SHA-256-sized hexadecimal value.');
        }
    }

    public function equals(self $other): bool
    {
        return hash_equals(strtolower($this->value), strtolower($other->value));
    }
}
