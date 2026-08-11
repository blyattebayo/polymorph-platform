<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Http;

use Polymorph\Platform\Domain\Auth\Application\SessionPolicy;
use Polymorph\Platform\Domain\Auth\Infrastructure\Config\SessionCookieConfig;
use Symfony\Component\HttpFoundation\Cookie;

final readonly class SessionCookie
{
    public const NAME = 'pmph_session';

    public const PATH = '/';

    public function __construct(private SessionCookieConfig $config) {}

    public function create(string $credential): Cookie
    {
        return $this->make($credential, SessionPolicy::LIFETIME_SECONDS);
    }

    public function forget(): Cookie
    {
        return $this->make('', -60);
    }

    private function make(string $value, int $ttl): Cookie
    {
        return Cookie::create(self::NAME, $value, now()->addSeconds($ttl))
            ->withSecure($this->config->secure)
            ->withHttpOnly(true)
            ->withSameSite($this->config->sameSite)
            ->withPath(self::PATH);
    }
}
