<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Contracts;

use Polymorph\Platform\Domain\Auth\Application\Models\IssuedSessionCredential;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\TokenHash;

interface SessionCredentials
{
    public function issue(): IssuedSessionCredential;

    public function hash(string $plainText): TokenHash;
}
