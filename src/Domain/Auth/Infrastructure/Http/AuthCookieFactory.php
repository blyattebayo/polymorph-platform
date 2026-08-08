<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Http;

use Polymorph\Platform\Domain\Auth\Core\ValueObjects\AuthCookieConfig;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\JwtConfig;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Выписывает и гасит auth-куки. Параметры приходят типизированными: приватный
 * config()-метод, который на каждый вызов пересобирал массив из config('jwt.*')
 * со своими дефолтами, больше не нужен.
 */
final class AuthCookieFactory
{
    public function __construct(
        private readonly AuthCookieConfig $config,
        private readonly JwtConfig $jwt,
    ) {}

    public function access(string $token): Cookie
    {
        return $this->make($this->config->accessName, $token, $this->minutes($this->jwt->accessTtl), $this->config->path);
    }

    public function refresh(string $token): Cookie
    {
        return $this->make($this->config->refreshName, $token, $this->minutes($this->jwt->refreshTtl), $this->config->refreshPath);
    }

    public function accessName(): string
    {
        return $this->config->accessName;
    }

    public function refreshName(): string
    {
        return $this->config->refreshName;
    }

    /**
     * @return list<Cookie>
     */
    public function pair(string $accessToken, string $refreshToken): array
    {
        return [
            $this->access($accessToken),
            $this->refresh($refreshToken),
        ];
    }

    /**
     * @return list<Cookie>
     */
    public function forgetPair(): array
    {
        return [
            $this->make($this->config->accessName, '', -1, $this->config->path),
            $this->make($this->config->refreshName, '', -1, $this->config->refreshPath),
        ];
    }

    private function make(string $name, string $value, int $minutes, string $path): Cookie
    {
        $sameSite = $this->normalizeSameSite($this->config->sameSite);

        return Cookie::create($name, $value, now()->addMinutes($minutes))
            ->withSecure($this->secure($sameSite))
            ->withHttpOnly(true)
            ->withSameSite($sameSite)
            ->withPath($path)
            ->withDomain($this->config->domain);
    }

    private function minutes(int $ttlSeconds): int
    {
        return (int) ceil($ttlSeconds / 60);
    }

    private function normalizeSameSite(string $sameSite): string
    {
        return match (strtolower(trim($sameSite))) {
            'none' => Cookie::SAMESITE_NONE,
            'lax' => Cookie::SAMESITE_LAX,
            'strict' => Cookie::SAMESITE_STRICT,
            default => Cookie::SAMESITE_STRICT,
        };
    }

    /** SameSite=None без Secure браузер отвергает — поднимаем флаг принудительно. */
    private function secure(string $sameSite): bool
    {
        return $sameSite === Cookie::SAMESITE_NONE || $this->config->secure;
    }
}
