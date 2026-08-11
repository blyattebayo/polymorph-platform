<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken;

use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenDigest;

interface PersonalAccessTokenSecretCodec
{
    public function generate(): PersonalAccessTokenSecret;

    /** Accepts only the exact credential format implemented by this codec. */
    public function supports(string $plaintext): bool;

    public function digest(string $plaintext): PersonalAccessTokenDigest;
}
