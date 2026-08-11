<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\OAuth;

use Polymorph\Platform\Domain\Auth\Application\OAuth\Data\OAuthSecret;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthSecrets;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\TokenHash;

final class SecureOAuthSecrets implements OAuthSecrets
{
    public function authorizationCode(): OAuthSecret
    {
        return $this->issue('pmph_oac_');
    }

    public function accessToken(): OAuthSecret
    {
        return $this->issue('pmph_oat_');
    }

    public function refreshToken(): OAuthSecret
    {
        return $this->issue('pmph_ort_');
    }

    public function hash(string $plaintext): TokenHash
    {
        return new TokenHash(hash('sha256', $plaintext));
    }

    private function issue(string $prefix): OAuthSecret
    {
        $plaintext = $prefix.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return new OAuthSecret($plaintext, $this->hash($plaintext));
    }
}
