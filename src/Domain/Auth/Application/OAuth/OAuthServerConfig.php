<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\OAuth;

use Polymorph\Platform\Domain\Auth\Application\Exceptions\AuthConfigurationException;

final readonly class OAuthServerConfig
{
    public const SCOPE = 'mcp';

    public const AUTHORIZATION_CODE_TTL_SECONDS = 300;

    public const ACCESS_TOKEN_TTL_SECONDS = 900;

    public const REFRESH_TOKEN_TTL_SECONDS = 2_592_000;

    private function __construct(
        public string $issuer,
        public string $resource,
        public int $authorizationCodeTtlSeconds,
        public int $accessTokenTtlSeconds,
        public int $refreshTokenTtlSeconds,
    ) {}

    public static function fromApplicationUrl(string $applicationUrl, bool $production = false): self
    {
        $issuer = rtrim(trim($applicationUrl), '/');

        self::assertApplicationUrl($issuer, $production);

        return new self(
            issuer: $issuer,
            resource: $issuer.'/api/v1/ext/context-router/protocol',
            authorizationCodeTtlSeconds: self::AUTHORIZATION_CODE_TTL_SECONDS,
            accessTokenTtlSeconds: self::ACCESS_TOKEN_TTL_SECONDS,
            refreshTokenTtlSeconds: self::REFRESH_TOKEN_TTL_SECONDS,
        );
    }

    public function authorizationEndpoint(): string
    {
        return $this->issuer.'/oauth/authorize';
    }

    public function tokenEndpoint(): string
    {
        return $this->issuer.'/oauth/token';
    }

    public function registrationEndpoint(): string
    {
        return $this->issuer.'/oauth/register';
    }

    public function revocationEndpoint(): string
    {
        return $this->issuer.'/oauth/revoke';
    }

    public function protectedResourceMetadataEndpoint(): string
    {
        return $this->issuer.'/.well-known/oauth-protected-resource';
    }

    private static function assertApplicationUrl(string $url, bool $production): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $isLoopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        if ($url === ''
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || $host === ''
            || ! in_array($scheme, ['http', 'https'], true)
            || parse_url($url, PHP_URL_FRAGMENT) !== null
            || parse_url($url, PHP_URL_USER) !== null
            || parse_url($url, PHP_URL_PASS) !== null
            || parse_url($url, PHP_URL_QUERY) !== null
            || ! in_array((string) parse_url($url, PHP_URL_PATH), ['', '/'], true)) {
            throw AuthConfigurationException::invalid('APP_URL must be an absolute HTTP(S) origin without path, query, credentials, or fragment.');
        }

        if ($production && $scheme !== 'https' && ! $isLoopback) {
            throw AuthConfigurationException::invalid('APP_URL must use HTTPS in production.');
        }
    }
}
