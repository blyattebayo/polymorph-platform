<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Authentication;

use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticatedCredential;
use Polymorph\Platform\Domain\Auth\Application\Authentication\RequestCredentialResolver;
use Polymorph\Platform\Domain\Auth\Application\Exceptions\AuthenticationDenied;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthAuthorizationServer;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthServerConfig;
use Polymorph\Platform\Domain\Auth\Infrastructure\Http\SessionCookie;

final readonly class OAuthAccessTokenCredentialAuthenticator implements RequestCredentialResolver
{
    public function __construct(
        private OAuthAuthorizationServer $server,
        private OAuthServerConfig $config,
    ) {}

    public function authenticate(Request $request): ?AuthenticatedCredential
    {
        $cookie = trim((string) $request->cookie(SessionCookie::NAME, ''));
        $bearer = trim((string) $request->bearerToken());

        if ($cookie !== '' && $bearer !== '') {
            throw AuthenticationDenied::ambiguousCredentials();
        }
        if ($bearer === '') {
            return null;
        }

        $user = $this->server->authenticate($bearer);
        if ($user === null) {
            return null;
        }

        return AuthenticatedCredential::oauthAccessToken($user);
    }

    public function challenge(): string
    {
        return sprintf(
            'Bearer resource_metadata="%s", scope="%s"',
            $this->config->protectedResourceMetadataEndpoint(),
            OAuthServerConfig::SCOPE,
        );
    }
}
