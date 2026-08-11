<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Authentication;

use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Application\Exceptions\AuthenticationDenied;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\UseCases\AuthenticatePersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Infrastructure\Http\SessionCookie;
use Polymorph\Platform\SharedKernel\Identity\AuthenticatedCredential;
use Polymorph\Platform\SharedKernel\Identity\RequestCredentialResolver;

final readonly class PersonalAccessTokenCredentialAuthenticator implements RequestCredentialResolver
{
    public function __construct(private AuthenticatePersonalAccessToken $tokens) {}

    public function authenticate(Request $request): ?AuthenticatedCredential
    {
        $bearer = trim((string) $request->bearerToken());
        $cookie = trim((string) $request->cookie(SessionCookie::NAME, ''));

        if ($cookie !== '' && $bearer !== '') {
            throw AuthenticationDenied::ambiguousCredentials();
        }

        if ($bearer === '') {
            return null;
        }

        $authenticated = $this->tokens->execute($bearer);
        if ($authenticated === null) {
            throw AuthenticationDenied::invalidAccessToken();
        }

        return AuthenticatedCredential::personalAccessToken(
            $authenticated->actor,
            $authenticated->scopes,
        );
    }
}
