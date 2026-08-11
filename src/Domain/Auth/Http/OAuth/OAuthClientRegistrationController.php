<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\OAuth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthAuthorizationServer;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthProtocolException;

final readonly class OAuthClientRegistrationController
{
    public function __construct(private OAuthAuthorizationServer $server) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            if ($this->mediaType($request) !== 'application/json') {
                throw new OAuthProtocolException('invalid_client_metadata', 'Client registration requires application/json.');
            }
            $this->assertMetadata($request);
            $redirects = $request->input('redirect_uris');
            if (! is_array($redirects)
                || ! array_is_list($redirects)
                || array_filter($redirects, static fn (mixed $uri): bool => ! is_string($uri)) !== []) {
                throw new OAuthProtocolException('invalid_redirect_uri', 'redirect_uris must be a JSON array of strings.');
            }
            $name = $request->input('client_name', 'MCP client');
            if (! is_string($name)) {
                throw new OAuthProtocolException('invalid_client_metadata', 'client_name must be a string.');
            }
            $client = $this->server->registerClient(
                $name,
                $redirects,
            );

            return response()->json([
                'client_id' => $client->id,
                'client_id_issued_at' => time(),
                'client_name' => $client->name,
                'redirect_uris' => $client->redirectUris,
                'token_endpoint_auth_method' => 'none',
                'grant_types' => ['authorization_code', 'refresh_token'],
                'response_types' => ['code'],
            ], 201)->withHeaders(['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
        } catch (OAuthProtocolException $error) {
            return OAuthResponses::error($error);
        }
    }

    private function assertMetadata(Request $request): void
    {
        if ($request->input('token_endpoint_auth_method', 'none') !== 'none') {
            throw new OAuthProtocolException('invalid_client_metadata', 'Only public clients with token_endpoint_auth_method=none are supported.');
        }

        foreach (['grant_types' => ['authorization_code', 'refresh_token'], 'response_types' => ['code']] as $field => $expected) {
            $requested = $request->input($field, $expected);
            if (! is_array($requested)
                || ! array_is_list($requested)
                || count($requested) !== count($expected)
                || array_filter($requested, static fn (mixed $value): bool => ! is_string($value)) !== []
                || array_diff($requested, $expected) !== []
                || array_diff($expected, $requested) !== []) {
                throw new OAuthProtocolException('invalid_client_metadata', "Unsupported {$field}.");
            }
        }
    }

    private function mediaType(Request $request): string
    {
        return strtolower(trim(explode(';', (string) $request->header('Content-Type', ''), 2)[0]));
    }
}
