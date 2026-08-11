<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\PersonalAccessToken\Security;

use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenSecret;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenSecretCodec;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenDigest;
use SensitiveParameter;

final class V1PersonalAccessTokenSecretCodec implements PersonalAccessTokenSecretCodec
{
    public const VERSION_PREFIX = 'pmph_pat_v1_';

    private const SECRET_BYTES = 32;

    private const SECRET_LENGTH = 43;

    private const DISPLAY_SECRET_LENGTH = 6;

    public function generate(): PersonalAccessTokenSecret
    {
        $secret = rtrim(strtr(base64_encode(random_bytes(self::SECRET_BYTES)), '+/', '-_'), '=');
        $plaintext = self::VERSION_PREFIX.$secret;

        return new PersonalAccessTokenSecret(
            plaintext: $plaintext,
            digest: $this->digest($plaintext),
            displayHint: self::VERSION_PREFIX.substr($secret, 0, self::DISPLAY_SECRET_LENGTH).'...',
        );
    }

    public function supports(#[SensitiveParameter] string $plaintext): bool
    {
        return preg_match(
            '/^'.preg_quote(self::VERSION_PREFIX, '/').'[A-Za-z0-9_-]{'.self::SECRET_LENGTH.'}$/D',
            $plaintext,
        ) === 1;
    }

    public function digest(#[SensitiveParameter] string $plaintext): PersonalAccessTokenDigest
    {
        return new PersonalAccessTokenDigest(hash('sha256', $plaintext));
    }
}
