<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\OAuth\Data;

use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\TokenHash;

final readonly class OAuthSecret
{
    public function __construct(
        public string $plaintext,
        public TokenHash $hash,
    ) {}
}
