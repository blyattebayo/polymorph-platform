<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Domain\ValueObjects;

use Polymorph\Platform\Domain\Auth\Domain\Exceptions\AuthInvariantViolation;

final readonly class UserId
{
    public function __construct(public int $value)
    {
        if ($value <= 0) {
            throw new AuthInvariantViolation('User id must be positive.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
