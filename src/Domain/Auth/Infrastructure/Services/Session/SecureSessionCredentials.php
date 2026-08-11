<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services\Session;

use Polymorph\Platform\Domain\Auth\Application\Contracts\SessionCredentials;
use Polymorph\Platform\Domain\Auth\Application\Models\IssuedSessionCredential;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\TokenHash;

final class SecureSessionCredentials implements SessionCredentials
{
    public function issue(): IssuedSessionCredential
    {
        $plainText = 'pmph_session_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return new IssuedSessionCredential($plainText, $this->hash($plainText));
    }

    public function hash(string $plainText): TokenHash
    {
        return new TokenHash(hash('sha256', $plainText));
    }
}
