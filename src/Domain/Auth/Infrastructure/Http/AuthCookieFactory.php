<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Cookie;

final class AuthCookieFactory
{
    /**
     * @return array{access: string, refresh: string, domain: mixed, secure: bool, samesite: string, path: string, refresh_path: string}
     */
    private function config(): array
    {
        $jwtCookies = (array) config('jwt.cookies', []);

        return [
            'access' => (string) ($jwtCookies['access'] ?? 'cms_at'),
            'refresh' => (string) ($jwtCookies['refresh'] ?? 'cms_rt'),
            'domain' => $jwtCookies['domain'] ?? null,
            'secure' => (bool) ($jwtCookies['secure'] ?? config('app.env') !== 'local'),
            'samesite' => (string) ($jwtCookies['samesite'] ?? 'Strict'),
            'path' => (string) ($jwtCookies['path'] ?? '/'),
            'refresh_path' => (string) ($jwtCookies['refresh_path'] ?? '/api/v1/auth/refresh'),
        ];
    }

    public function access(string $token): Cookie
    {
        $config = $this->config();
        $minutes = (int) ceil(((int) config('jwt.access_ttl', 900)) / 60);
        $sameSite = $this->normalizeSameSite((string) $config['samesite']);

        return Cookie::create($config['access'], $token, now()->addMinutes($minutes))
            ->withSecure($this->secure($sameSite, (bool) $config['secure']))
            ->withHttpOnly(true)
            ->withSameSite($sameSite)
            ->withPath($config['path'])
            ->withDomain($config['domain']);
    }

    public function refresh(string $token): Cookie
    {
        $config = $this->config();
        $minutes = (int) ceil(((int) config('jwt.refresh_ttl', 2592000)) / 60);
        $sameSite = $this->normalizeSameSite((string) $config['samesite']);

        return Cookie::create($config['refresh'], $token, now()->addMinutes($minutes))
            ->withSecure($this->secure($sameSite, (bool) $config['secure']))
            ->withHttpOnly(true)
            ->withSameSite($sameSite)
            ->withPath($config['refresh_path'])
            ->withDomain($config['domain']);
    }

    public function refreshName(): string
    {
        return $this->config()['refresh'];
    }

    public function accessName(): string
    {
        return $this->config()['access'];
    }

    public function forgetAccess(): Cookie
    {
        $config = $this->config();
        $sameSite = $this->normalizeSameSite((string) $config['samesite']);

        return Cookie::create($config['access'], '', now()->subMinutes(1))
            ->withSecure($this->secure($sameSite, (bool) $config['secure']))
            ->withHttpOnly(true)
            ->withSameSite($sameSite)
            ->withPath($config['path'])
            ->withDomain($config['domain']);
    }

    public function forgetRefresh(): Cookie
    {
        $config = $this->config();
        $sameSite = $this->normalizeSameSite((string) $config['samesite']);

        return Cookie::create($config['refresh'], '', now()->subMinutes(1))
            ->withSecure($this->secure($sameSite, (bool) $config['secure']))
            ->withHttpOnly(true)
            ->withSameSite($sameSite)
            ->withPath($config['refresh_path'])
            ->withDomain($config['domain']);
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
            $this->forgetAccess(),
            $this->forgetRefresh(),
        ];
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

    private function secure(string $sameSite, bool $configured): bool
    {
        return $sameSite === Cookie::SAMESITE_NONE || $configured;
    }
}
