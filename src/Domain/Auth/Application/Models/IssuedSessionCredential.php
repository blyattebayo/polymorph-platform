<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Models;

use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\TokenHash;

final readonly class IssuedSessionCredential
{
    public function __construct(
        public string $plainText,
        public TokenHash $hash,
    ) {}
}
