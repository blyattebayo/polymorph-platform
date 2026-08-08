<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\ValueObjects;

/**
 * Параметры auth-кук. Собираются один раз в AuthServiceProvider; ниже по коду
 * config('jwt.cookies.*') не читается.
 *
 * Раньше имена кук знали трое: AuthCookieFactory (выписывает),
 * PresentedTokenExtractor (читает) и лимитер в AppServiceProvider — каждый со
 * своим дефолтом на случай пустого конфига.
 */
final readonly class AuthCookieConfig
{
    public function __construct(
        public string $accessName,
        public string $refreshName,
        public ?string $domain,
        public bool $secure,
        public string $sameSite,
        public string $path,
        public string $refreshPath,
    ) {}

    /**
     * @param  array<string, mixed>  $cookies  секция jwt.cookies
     */
    public static function fromArray(array $cookies): self
    {
        $domain = $cookies['domain'] ?? null;

        return new self(
            accessName: self::text($cookies['access'] ?? null, 'cms_at'),
            refreshName: self::text($cookies['refresh'] ?? null, 'cms_rt'),
            domain: is_string($domain) && trim($domain) !== '' ? $domain : null,
            // Дефолт по окружению задан в config/jwt.php; здесь — только
            // страховка на случай пустой секции, и она в безопасную сторону.
            secure: (bool) ($cookies['secure'] ?? true),
            sameSite: self::text($cookies['samesite'] ?? null, 'Strict'),
            path: self::text($cookies['path'] ?? null, '/'),
            refreshPath: self::text($cookies['refresh_path'] ?? null, '/api/v1/auth/refresh'),
        );
    }

    private static function text(mixed $value, string $fallback): string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';

        return $text === '' ? $fallback : $text;
    }
}
