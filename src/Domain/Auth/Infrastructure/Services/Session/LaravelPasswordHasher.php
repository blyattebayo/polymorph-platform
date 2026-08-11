<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services\Session;

use Illuminate\Support\Facades\Hash;
use Polymorph\Platform\Domain\Auth\Application\Contracts\PasswordHasher;

final class LaravelPasswordHasher implements PasswordHasher
{
    public function verify(string $plainText, string $hash): bool
    {
        return Hash::check($plainText, $hash);
    }

    public function hash(string $plainText): string
    {
        return Hash::make($plainText);
    }
}
