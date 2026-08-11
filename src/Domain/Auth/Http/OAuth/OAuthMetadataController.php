<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\OAuth;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthServerConfig;

final readonly class OAuthMetadataController
{
    public function __construct(private OAuthServerConfig $config) {}

    public function authorizationServer(): JsonResponse
    {
        return $this->json([
            'issuer' => $this->config->issuer,
            'authorization_endpoint' => $this->config->authorizationEndpoint(),
            'token_endpoint' => $this->config->tokenEndpoint(),
            'registration_endpoint' => $this->config->registrationEndpoint(),
            'revocation_endpoint' => $this->config->revocationEndpoint(),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => [OAuthServerConfig::SCOPE],
        ]);
    }

    public function protectedResource(): JsonResponse
    {
        return $this->json([
            'resource' => $this->config->resource,
            'authorization_servers' => [$this->config->issuer],
            'scopes_supported' => [OAuthServerConfig::SCOPE],
            'bearer_methods_supported' => ['header'],
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload): JsonResponse
    {
        return response()->json($payload)->withHeaders(['Cache-Control' => 'public, max-age=300']);
    }
}
