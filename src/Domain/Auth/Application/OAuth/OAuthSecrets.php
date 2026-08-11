<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\OAuth;

use Polymorph\Platform\Domain\Auth\Application\OAuth\Data\OAuthSecret;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\TokenHash;

interface OAuthSecrets
{
    public function authorizationCode(): OAuthSecret;

    public function accessToken(): OAuthSecret;

    public function refreshToken(): OAuthSecret;

    public function hash(string $plaintext): TokenHash;
}
