<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Authentication;

use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Application\Contracts\Clock;
use Polymorph\Platform\Domain\Auth\Application\Contracts\SessionCredentials;
use Polymorph\Platform\Domain\Auth\Application\Contracts\SessionRepository;
use Polymorph\Platform\Domain\Auth\Application\Exceptions\AuthenticationDenied;
use Polymorph\Platform\Domain\Auth\Infrastructure\Http\SessionCookie;
use Polymorph\Platform\SharedKernel\Identity\AuthenticatedCredential;
use Polymorph\Platform\SharedKernel\Identity\RequestCredentialResolver;

final readonly class SessionCredentialAuthenticator implements RequestCredentialResolver
{
    public function __construct(
        private SessionCredentials $credentials,
        private SessionRepository $sessions,
        private Clock $clock,
    ) {}

    public function authenticate(Request $request): ?AuthenticatedCredential
    {
        $cookie = trim((string) $request->cookie(SessionCookie::NAME, ''));
        $bearer = trim((string) $request->bearerToken());

        if ($cookie !== '' && $bearer !== '') {
            throw AuthenticationDenied::ambiguousCredentials();
        }

        if ($cookie === '') {
            return null;
        }

        $session = $this->sessions->findAuthenticated($this->credentials->hash($cookie), $this->clock->now());
        if ($session === null) {
            throw AuthenticationDenied::invalidAccessToken();
        }

        return AuthenticatedCredential::session($session->user, (string) $session->sessionId);
    }
}
