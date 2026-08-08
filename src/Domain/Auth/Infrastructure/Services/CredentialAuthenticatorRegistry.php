<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services;

use Polymorph\Platform\Domain\Auth\Core\Contracts\CredentialAuthenticator;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\PresentedToken;
use Polymorph\Platform\SharedKernel\Identity\AuthenticatedCredential;

/**
 * Реестр способов аутентификации. Порядок — порядок регистрации в
 * AuthServiceProvider; побеждает первый, чей supports() сказал «мой».
 * Способа-фолбэка нет: токен, который не опознал никто, — просто невалидный
 * токен.
 */
final class CredentialAuthenticatorRegistry
{
    /**
     * @param  iterable<CredentialAuthenticator>  $authenticators
     */
    public function __construct(
        private readonly iterable $authenticators,
    ) {}

    public function authenticate(PresentedToken $token): ?AuthenticatedCredential
    {
        foreach ($this->authenticators as $authenticator) {
            if ($authenticator->supports($token)) {
                return $authenticator->attempt($token);
            }
        }

        return null;
    }
}
