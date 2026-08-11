<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\OAuth;

use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthAuthorizationServer;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthProtocolException;

final readonly class OAuthRevocationController
{
    public function __construct(private OAuthAuthorizationServer $server) {}

    public function __invoke(Request $request): mixed
    {
        try {
            if (strtolower(trim(explode(';', (string) $request->header('Content-Type', ''), 2)[0])) !== 'application/x-www-form-urlencoded') {
                throw new OAuthProtocolException('invalid_request', 'The revocation endpoint requires application/x-www-form-urlencoded.');
            }
            if (trim((string) $request->header('Authorization', '')) !== '') {
                throw new OAuthProtocolException('invalid_client', 'This authorization server accepts public clients without client authentication.', 401);
            }
            $this->server->revoke(
                trim((string) $request->input('client_id', '')),
                (string) $request->input('token', ''),
            );

            return response('', 200)->withHeaders(['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
        } catch (OAuthProtocolException $error) {
            return OAuthResponses::error($error);
        }
    }
}
