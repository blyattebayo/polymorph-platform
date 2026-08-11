<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\OAuth;

use RuntimeException;

final class OAuthProtocolException extends RuntimeException
{
    public function __construct(
        public readonly string $error,
        string $description,
        public readonly int $status = 400,
    ) {
        parent::__construct($description);
    }
}
