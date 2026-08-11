<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\OAuth;

use Polymorph\Platform\Domain\Auth\Application\Contracts\Clock;
use Polymorph\Platform\Domain\Auth\Application\Contracts\IdGenerator;
use Polymorph\Platform\Domain\Auth\Application\OAuth\Data\AuthorizationCode;
use Polymorph\Platform\Domain\Auth\Application\OAuth\Data\AuthorizationRequest;
use Polymorph\Platform\Domain\Auth\Application\OAuth\Data\OAuthClient;
use Polymorph\Platform\Domain\Auth\Application\OAuth\Data\OAuthGrant;
use Polymorph\Platform\Domain\Auth\Application\OAuth\Data\OAuthTokenSet;
use Polymorph\Platform\Domain\Users\Infrastructure\Repositories\UserRepository;
use Polymorph\Platform\Domain\Users\Core\Models\User;

final readonly class OAuthAuthorizationServer
{
    private const CURSOR_DESKTOP_REDIRECT_URI = 'cursor://anysphere.cursor-mcp/oauth/callback';

    public function __construct(
        private OAuthServerConfig $config,
        private OAuthStore $store,
        private OAuthSecrets $secrets,
        private Clock $clock,
        private IdGenerator $ids,
        private UserRepository $users,
    ) {}

    /** @param list<string> $redirectUris */
    public function registerClient(string $name, array $redirectUris): OAuthClient
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 200) {
            throw new OAuthProtocolException('invalid_client_metadata', 'client_name must contain 1 to 200 characters.');
        }

        $redirectUris = array_values(array_unique(array_map('trim', $redirectUris)));
        if ($redirectUris === [] || count($redirectUris) > 10) {
            throw new OAuthProtocolException('invalid_redirect_uri', 'Provide between one and ten redirect_uris.');
        }
        foreach ($redirectUris as $uri) {
            $this->assertRedirectUri($uri);
        }

        $client = new OAuthClient($this->ids->uuid(), $name, $redirectUris);
        $this->store->registerClient($client->id, $client->name, $client->redirectUris, $this->clock->now());

        return $client;
    }

    /** @param array<string, mixed> $input */
    public function validateAuthorizationRequest(array $input): AuthorizationRequest
    {
        if (($input['response_type'] ?? null) !== 'code') {
            throw new OAuthProtocolException('unsupported_response_type', 'Only response_type=code is supported.');
        }

        $client = $this->store->client(trim((string) ($input['client_id'] ?? '')));
        if (! $client instanceof OAuthClient) {
            throw new OAuthProtocolException('invalid_request', 'Unknown client_id.');
        }

        $redirectUri = trim((string) ($input['redirect_uri'] ?? ''));
        if (! $client->acceptsRedirect($redirectUri)) {
            throw new OAuthProtocolException('invalid_request', 'redirect_uri does not exactly match the registered URI.');
        }

        $resource = trim((string) ($input['resource'] ?? ''));
        if ($resource !== $this->config->resource) {
            throw new OAuthProtocolException('invalid_target', 'resource must identify this MCP gateway.');
        }

        $scope = $this->scope((string) ($input['scope'] ?? ''));
        $challenge = trim((string) ($input['code_challenge'] ?? ''));
        if (($input['code_challenge_method'] ?? null) !== 'S256' || preg_match('/^[A-Za-z0-9_-]{43}$/', $challenge) !== 1) {
            throw new OAuthProtocolException('invalid_request', 'A valid S256 PKCE code_challenge is required.');
        }

        $state = array_key_exists('state', $input) ? (string) $input['state'] : null;
        if ($state !== null && strlen($state) > 2048) {
            throw new OAuthProtocolException('invalid_request', 'state is too long.');
        }

        return new AuthorizationRequest($client, $redirectUri, $resource, $scope, $challenge, $state);
    }

    public function approve(AuthorizationRequest $request, User $user): string
    {
        if (! $user->isActiveAccount()) {
            throw new OAuthProtocolException('access_denied', 'The user account is not active.', 403);
        }

        $secret = $this->secrets->authorizationCode();
        $now = $this->clock->now();
        $this->store->saveAuthorizationCode(
            $secret->hash,
            new AuthorizationCode(
                $request->client->id,
                (int) $user->id,
                $request->redirectUri,
                $request->resource,
                $request->scope,
                $request->codeChallenge,
            ),
            $now,
            $now->modify('+'.$this->config->authorizationCodeTtlSeconds.' seconds'),
        );

        return $secret->plaintext;
    }

    public function exchangeAuthorizationCode(
        string $clientId,
        string $plaintextCode,
        string $redirectUri,
        string $verifier,
        string $resource,
    ): OAuthTokenSet {
        $this->assertResource($resource);
        $code = $this->store->consumeAuthorizationCode($this->secrets->hash($plaintextCode), $this->clock->now());
        if (! $code instanceof AuthorizationCode
            || $code->clientId !== $clientId
            || $code->redirectUri !== $redirectUri
            || $code->resource !== $resource
            || ! $this->matchesPkce($verifier, $code->codeChallenge)) {
            throw new OAuthProtocolException('invalid_grant', 'Authorization code is invalid, expired, consumed, or bound to another request.');
        }

        $user = $this->users->find($code->userId);
        if ($user === null || ! $user->isActiveAccount()) {
            throw new OAuthProtocolException('invalid_grant', 'The authorizing user is no longer active.');
        }

        $now = $this->clock->now();
        $access = $this->secrets->accessToken();
        $refresh = $this->secrets->refreshToken();
        $grant = new OAuthGrant($this->ids->uuid(), $clientId, $code->userId, $resource, $code->scope);
        $this->store->createGrant(
            $grant,
            $refresh->hash,
            $now->modify('+'.$this->config->refreshTokenTtlSeconds.' seconds'),
            $access->hash,
            $now->modify('+'.$this->config->accessTokenTtlSeconds.' seconds'),
            $now,
        );

        return new OAuthTokenSet($access->plaintext, $refresh->plaintext, $this->config->accessTokenTtlSeconds, $code->scope);
    }

    public function refresh(string $clientId, string $plaintextRefreshToken, string $resource, string $scope = ''): OAuthTokenSet
    {
        $this->assertResource($resource);
        if ($scope !== '') {
            $this->scope($scope);
        }

        $now = $this->clock->now();
        $access = $this->secrets->accessToken();
        $refresh = $this->secrets->refreshToken();
        $grant = $this->store->rotateRefreshToken(
            $this->secrets->hash($plaintextRefreshToken),
            $clientId,
            $resource,
            $refresh->hash,
            $now->modify('+'.$this->config->refreshTokenTtlSeconds.' seconds'),
            $access->hash,
            $now->modify('+'.$this->config->accessTokenTtlSeconds.' seconds'),
            $now,
        );
        if (! $grant instanceof OAuthGrant) {
            throw new OAuthProtocolException('invalid_grant', 'Refresh token is invalid, expired, already rotated, or bound to another client.');
        }
        if ($this->activeIdentity($grant->userId) === null) {
            $this->store->revoke($refresh->hash, $clientId);

            throw new OAuthProtocolException('invalid_grant', 'The authorizing user is no longer active.');
        }

        return new OAuthTokenSet($access->plaintext, $refresh->plaintext, $this->config->accessTokenTtlSeconds, $grant->scope);
    }

    public function authenticate(string $plaintextAccessToken): ?User
    {
        if (! str_starts_with($plaintextAccessToken, 'pmph_oat_')) {
            return null;
        }

        $grant = $this->store->grantForAccessToken(
            $this->secrets->hash($plaintextAccessToken),
            $this->config->resource,
            $this->clock->now(),
        );

        return $grant instanceof OAuthGrant ? $this->activeIdentity($grant->userId) : null;
    }

    public function revoke(string $clientId, string $plaintextToken): void
    {
        if ($plaintextToken === '') {
            return;
        }

        $this->store->revoke($this->secrets->hash($plaintextToken), $clientId);
    }

    private function scope(string $scope): string
    {
        $scope = trim($scope);
        if ($scope === '') {
            return OAuthServerConfig::SCOPE;
        }
        if ($scope !== OAuthServerConfig::SCOPE) {
            throw new OAuthProtocolException('invalid_scope', 'Only the mcp scope is supported.');
        }

        return $scope;
    }

    private function assertResource(string $resource): void
    {
        if ($resource !== $this->config->resource) {
            throw new OAuthProtocolException('invalid_target', 'resource must identify this MCP gateway.');
        }
    }

    private function matchesPkce(string $verifier, string $challenge): bool
    {
        if (preg_match('/^[A-Za-z0-9.\\-_~]{43,128}$/', $verifier) !== 1) {
            return false;
        }

        $calculated = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return hash_equals($challenge, $calculated);
    }

    private function assertRedirectUri(string $uri): void
    {
        if ($uri === self::CURSOR_DESKTOP_REDIRECT_URI) {
            return;
        }

        $scheme = strtolower((string) parse_url($uri, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($uri, PHP_URL_HOST));
        $fragment = parse_url($uri, PHP_URL_FRAGMENT);
        $isLoopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        if ($uri === ''
            || strlen($uri) > 2048
            || filter_var($uri, FILTER_VALIDATE_URL) === false
            || $host === ''
            || $fragment !== null
            || parse_url($uri, PHP_URL_USER) !== null
            || parse_url($uri, PHP_URL_PASS) !== null
            || ($scheme !== 'https' && ! ($scheme === 'http' && $isLoopback))) {
            throw new OAuthProtocolException('invalid_redirect_uri', 'redirect_uris must use HTTPS, except HTTP loopback callbacks.');
        }
    }

    private function activeIdentity(int $userId): ?User
    {
        $user = $this->users->find($userId);

        return $user !== null && $user->isActiveAccount() ? $user : null;
    }
}
