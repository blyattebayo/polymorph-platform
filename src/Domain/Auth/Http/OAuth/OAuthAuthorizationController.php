<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\OAuth;

use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\Domain\Auth\Application\OAuth\Data\AuthorizationRequest;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthAuthorizationServer;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthProtocolException;

final readonly class OAuthAuthorizationController
{
    public function __construct(
        private OAuthAuthorizationServer $server,
        private AuthenticationContext $authentication,
    ) {}

    public function show(Request $request): mixed
    {
        try {
            $authorization = $this->server->validateAuthorizationRequest($request->query());
        } catch (OAuthProtocolException $error) {
            return OAuthResponses::error($error);
        }

        if ($this->authentication->user() === null) {
            return redirect()->to($this->loginUrl($request->getRequestUri()));
        }

        return view('oauth.authorize', ['authorization' => $authorization]);
    }

    public function decide(Request $request): mixed
    {
        try {
            if (strtolower(trim(explode(';', (string) $request->header('Content-Type', ''), 2)[0])) !== 'application/x-www-form-urlencoded') {
                throw new OAuthProtocolException('invalid_request', 'Authorization consent requires an HTML form submission.');
            }
            $authorization = $this->server->validateAuthorizationRequest($request->all());
        } catch (OAuthProtocolException $error) {
            return OAuthResponses::error($error);
        }

        $actor = $this->authentication->user();
        if ($actor === null) {
            return redirect()->to(
                $this->loginUrl('/oauth/authorize?'.http_build_query($this->authorizationQuery($authorization))),
                303,
            );
        }

        if ($request->input('decision') !== 'approve') {
            return redirect()->away($this->callbackUrl($authorization, ['error' => 'access_denied']), 303);
        }

        try {
            $code = $this->server->approve($authorization, $actor);

            return redirect()->away($this->callbackUrl($authorization, ['code' => $code]), 303);
        } catch (OAuthProtocolException $error) {
            return redirect()->away($this->callbackUrl($authorization, [
                'error' => $error->error,
                'error_description' => $error->getMessage(),
            ]), 303);
        }
    }

    private function loginUrl(string $returnTo): string
    {
        $adminPath = trim((string) config('admin.path', 'admin'), '/');

        return '/'.$adminPath.'/login?return_to='.rawurlencode($returnTo);
    }

    /** @param array<string, string> $parameters */
    private function callbackUrl(AuthorizationRequest $authorization, array $parameters): string
    {
        if ($authorization->state !== null) {
            $parameters['state'] = $authorization->state;
        }

        return $authorization->redirectUri.(str_contains($authorization->redirectUri, '?') ? '&' : '?').http_build_query($parameters);
    }

    /** @return array<string, string> */
    private function authorizationQuery(AuthorizationRequest $authorization): array
    {
        return array_filter([
            'response_type' => 'code',
            'client_id' => $authorization->client->id,
            'redirect_uri' => $authorization->redirectUri,
            'resource' => $authorization->resource,
            'scope' => $authorization->scope,
            'code_challenge' => $authorization->codeChallenge,
            'code_challenge_method' => 'S256',
            'state' => $authorization->state,
        ], static fn (?string $value): bool => $value !== null);
    }
}
