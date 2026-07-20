<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services;

use Polymorph\Platform\Domain\Auth\Core\ValueObjects\AuthenticatedCredential;

final class CompositeCredentialAuthenticator
{
    public function __construct(
        private readonly PatCredentialAuthenticator $pat,
        private readonly JwtCredentialAuthenticator $jwt,
    ) {
    }

    public function authenticate(string $token): ?AuthenticatedCredential
    {
        if ($this->pat->looksLikePat($token)) {
            return $this->pat->authenticate($token);
        }

        return $this->jwt->authenticate($token);
    }
}
