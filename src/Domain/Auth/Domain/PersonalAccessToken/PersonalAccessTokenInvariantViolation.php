<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken;

use LogicException;

final class PersonalAccessTokenInvariantViolation extends LogicException
{
    public function __construct(
        public readonly PersonalAccessTokenInvariant $invariant,
        string $message,
    ) {
        parent::__construct($message);
    }
}
