<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\OAuth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthAuthorizationServer;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthProtocolException;

final readonly class OAuthTokenController
{
    public function __construct(private OAuthAuthorizationServer $server) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            if (strtolower(trim(explode(';', (string) $request->header('Content-Type', ''), 2)[0])) !== 'application/x-www-form-urlencoded') {
                throw new OAuthProtocolException('invalid_request', 'The token endpoint requires application/x-www-form-urlencoded.');
            }
            if (trim((string) $request->header('Authorization', '')) !== '') {
                throw new OAuthProtocolException('invalid_client', 'This authorization server accepts public clients without client authentication.', 401);
            }

            $clientId = trim((string) $request->input('client_id', ''));
            $resource = trim((string) $request->input('resource', ''));
            $grantType = (string) $request->input('grant_type', '');

            $tokens = match ($grantType) {
                'authorization_code' => $this->server->exchangeAuthorizationCode(
                    $clientId,
                    (string) $request->input('code', ''),
                    (string) $request->input('redirect_uri', ''),
                    (string) $request->input('code_verifier', ''),
                    $resource,
                ),
                'refresh_token' => $this->server->refresh(
                    $clientId,
                    (string) $request->input('refresh_token', ''),
                    $resource,
                    trim((string) $request->input('scope', '')),
                ),
                default => throw new OAuthProtocolException('unsupported_grant_type', 'Only authorization_code and refresh_token grants are supported.'),
            };

            return OAuthResponses::tokens($tokens);
        } catch (OAuthProtocolException $error) {
            return OAuthResponses::error($error);
        }
    }
}
