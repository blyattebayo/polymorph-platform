<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Contracts;

interface PasswordHasher
{
    public function verify(string $plainText, string $hash): bool;

    public function hash(string $plainText): string;
}
