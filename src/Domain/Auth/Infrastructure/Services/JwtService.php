<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services;

use Carbon\CarbonImmutable;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Str;
use Polymorph\Platform\Domain\Auth\Core\Exceptions\JwtConfigurationException;
use Polymorph\Platform\Domain\Auth\Core\Exceptions\JwtVerificationException;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\JwtConfig;

/**
 * Service for issuing and verifying JWT access and refresh tokens.
 *
 * Uses HS256 (HMAC with SHA-256) algorithm with a secret key.
 */
final class JwtService
{
    private readonly JwtConfig $config;

    /**
     * @param  JwtConfig|array<string, mixed>  $config
     */
    public function __construct(JwtConfig|array $config)
    {
        $this->config = is_array($config) ? JwtConfig::fromArray($config) : $config;
    }

    /**
     * Issue a new access token for the given user.
     *
     * @param  int|string  $userId  User identifier
     * @param  array  $extra  Additional claims to include in the payload
     * @return string JWT token string
     */
    public function issueAccessToken(int|string $userId, array $extra = []): string
    {
        return $this->encode($userId, 'access', $this->config->accessTtl, $extra);
    }

    /**
     * Issue a new refresh token for the given user.
     *
     * @param  int|string  $userId  User identifier
     * @param  array  $extra  Additional claims to include in the payload
     * @return string JWT token string
     */
    public function issueRefreshToken(int|string $userId, array $extra = []): string
    {
        return $this->encode($userId, 'refresh', $this->config->refreshTtl, $extra);
    }

    /**
     * Encode a JWT with the specified parameters.
     *
     * @param  int|string  $userId  User identifier
     * @param  string  $type  Token type ('access' or 'refresh')
     * @param  int  $ttl  Time-to-live in seconds
     * @param  array  $extra  Additional claims
     * @return string JWT token string
     *
     * @throws RuntimeException If the secret key is not configured
     */
    public function encode(int|string $userId, string $type, int $ttl, array $extra = []): string
    {
        $now = CarbonImmutable::now('UTC');
        $secret = $this->getSecret();

        // Merge standard claims with extra claims.
        // Security hardening: extra claims are not allowed to override reserved JWT claims.
        $standardClaims = [
            'iss' => $this->config->issuer,
            'aud' => $this->config->audience,
            'iat' => $now->getTimestamp(),
            'nbf' => $now->getTimestamp(),
            'exp' => $now->addSeconds($ttl)->getTimestamp(),
            'jti' => (string) Str::uuid(),
            'sub' => (string) $userId,
            'typ' => $type,
        ];

        $payload = array_merge($standardClaims, $this->filterExtraClaims($extra));

        return JWT::encode($payload, $secret, $this->config->algo, $this->config->currentKid);
    }

    /**
     * Verify a JWT and return its claims.
     *
     * @param  string  $jwt  The JWT token to verify
     * @param  string|null  $expectType  Expected token type ('access' or 'refresh'), or null to skip type check
     * @return array{claims: array} Decoded claims
     *
     * @throws JwtConfigurationException If the secret key is not configured
     * @throws JwtVerificationException If token type or issuer doesn't match expectations
     * @throws ExpiredException If the token has expired
     * @throws SignatureInvalidException If the signature is invalid
     * @throws BeforeValidException If the token is not yet valid
     */
    public function verify(string $jwt, ?string $expectType = null): array
    {
        $secret = $this->getSecret();

        $decoded = JWT::decode($jwt, $this->verificationKey());
        // Convert stdClass to array: json_encode/json_decode guarantees scalarization of types
        // (using (array)$decoded would break nested objects)
        $claims = json_decode(json_encode($decoded), true);

        // Verify token type if specified
        if ($expectType !== null && ($claims['typ'] ?? null) !== $expectType) {
            throw JwtVerificationException::invalidTokenType($expectType, $claims['typ']);
        }

        // Verify issuer (must match)
        if (($claims['iss'] ?? '') !== $this->config->issuer) {
            throw JwtVerificationException::invalidIssuer($claims['iss']);
        }

        // Verify audience (must include configured audience).
        if (! $this->isAudienceValid($claims['aud'] ?? null)) {
            throw JwtVerificationException::invalidAudience($claims['aud'] ?? null);
        }

        return ['claims' => $claims];
    }

    /**
     * Get the JWT secret key from configuration.
     *
     * @return string Secret key
     *
     * @throws JwtConfigurationException If the secret key is not configured
     */
    private function getSecret(): string
    {
        $secret = $this->config->keys[$this->config->currentKid] ?? $this->config->secret;

        if (empty($secret)) {
            throw JwtConfigurationException::missingSecret();
        }

        return $secret;
    }

    /**
     * @return Key|array<string, Key>
     */
    private function verificationKey(): Key|array
    {
        if (count($this->config->keys) <= 1) {
            return new Key($this->getSecret(), $this->config->algo);
        }

        $keys = [];
        foreach ($this->config->keys as $kid => $secret) {
            $keys[$kid] = new Key($secret, $this->config->algo);
        }

        return $keys;
    }

    /**
     * @param  array<string,mixed>  $extra
     * @return array<string,mixed>
     */
    private function filterExtraClaims(array $extra): array
    {
        $reservedClaims = [
            'iss',
            'aud',
            'iat',
            'nbf',
            'exp',
            'jti',
            'sub',
            'typ',
        ];

        return array_diff_key($extra, array_flip($reservedClaims));
    }

    private function isAudienceValid(mixed $audienceClaim): bool
    {
        $expectedAudience = $this->config->audience;
        if ($expectedAudience === '') {
            return false;
        }

        if (is_string($audienceClaim)) {
            return trim($audienceClaim) === $expectedAudience;
        }

        if (is_array($audienceClaim)) {
            foreach ($audienceClaim as $audienceValue) {
                if (is_scalar($audienceValue) && trim((string) $audienceValue) === $expectedAudience) {
                    return true;
                }
            }
        }

        return false;
    }
}
