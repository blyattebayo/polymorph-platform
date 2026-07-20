<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\ValueObjects;

final readonly class JwtConfig
{
    public function __construct(
        public string $algo,
        public int $accessTtl,
        public int $refreshTtl,
        public string $secret,
        public string $currentKid,
        public array $keys,
        public string $issuer,
        public string $audience,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            algo: (string) ($config['algo'] ?? 'HS256'),
            accessTtl: (int) ($config['access_ttl'] ?? 900),
            refreshTtl: (int) ($config['refresh_ttl'] ?? 2592000),
            secret: (string) ($config['secret'] ?? ''),
            currentKid: (string) ($config['current_kid'] ?? 'default'),
            keys: self::normalizeKeys($config),
            issuer: (string) ($config['issuer'] ?? ''),
            audience: (string) ($config['audience'] ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    private static function normalizeKeys(array $config): array
    {
        $keys = [];
        foreach ((array) ($config['keys'] ?? []) as $kid => $secret) {
            if (is_string($kid) && $kid !== '' && is_string($secret) && $secret !== '') {
                $keys[$kid] = $secret;
            }
        }

        $fallbackSecret = (string) ($config['secret'] ?? '');
        if ($keys === [] && $fallbackSecret !== '') {
            $keys[(string) ($config['current_kid'] ?? 'default')] = $fallbackSecret;
        }

        return $keys;
    }
}
