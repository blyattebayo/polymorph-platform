<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\PresentedToken;
use Polymorph\Platform\Domain\Auth\Infrastructure\Http\AuthCookieFactory;
use Polymorph\Platform\Domain\Auth\Infrastructure\Http\PresentedTokenExtractor;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\JwtService;
use Polymorph\Platform\SharedKernel\Identity\AuthenticatedCredential;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Скользящая сессия (sliding session) для cookie-аутентифицированных клиентов.
 *
 * Пока пользователь активен, на каждом аутентифицированном ответе access-кука
 * перевыпускается с полным TTL — поэтому короткий срок access-токена работает
 * как idle-timeout, а не как жёсткий выброс каждые N минут. Продление ограничено
 * абсолютным потолком семьи сессий (claim `aex`), поэтому общий срок жизни сессии
 * не превышает refresh_family_ttl даже у непрерывно активного пользователя.
 *
 * Не трогаем: Bearer/PAT-клиентов (управляют токенами сами), эндпоинты, которые
 * сами выставляют access-куку (login/refresh/logout), и токены за пределами `aex`.
 */
final class RenewAccessTokenCookie
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly AuthCookieFactory $cookies,
        private readonly AuthenticationContext $context,
        private readonly PresentedTokenExtractor $tokens,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof Response) {
            return $response;
        }

        $credential = $this->context->credential();

        // Только cookie-сессии (не PAT) и только когда запрос реально прошёл
        // аутентификацию ядра (denylist по sid уже проверен гардом).
        if (! $credential instanceof AuthenticatedCredential
            || ! $credential->isSession()
            || $credential->sessionId === null) {
            return $response;
        }

        // Bearer-клиенты не используют куку — продлевать нечего. Канал доставки
        // теперь спрашиваем у самого токена, а не вторым обращением к запросу.
        $token = $this->tokens->fromRequest($request);
        if (! $token instanceof PresentedToken || ! $token->isCookie()) {
            return $response;
        }

        // Эндпоинт уже сам управляет access-кукой (login/refresh/logout) — не мешаем.
        if ($this->responseManagesAccessCookie($response)) {
            return $response;
        }

        try {
            $claims = $this->jwt->verify($token->value, 'access')['claims'] ?? [];
        } catch (\Throwable) {
            return $response;
        }

        $now = time();
        $accessTtl = (int) config('jwt.access_ttl', 900);

        // Порог: продлеваем только во второй половине жизни токена — чтобы не
        // слать Set-Cookie на каждый запрос активной сессии.
        $exp = isset($claims['exp']) ? (int) $claims['exp'] : 0;
        if ($exp > 0 && ($exp - $now) > intdiv($accessTtl, 2)) {
            return $response;
        }

        // Абсолютный потолок: если задан и достигнут — даём токену истечь.
        $absoluteExpiresAt = isset($claims['aex']) ? (int) $claims['aex'] : $this->fallbackAbsoluteExpiry($now);
        if ($absoluteExpiresAt > 0 && $now >= $absoluteExpiresAt) {
            return $response;
        }

        $renewed = $this->jwt->issueAccessToken((int) $credential->user->id, [
            'sid' => $credential->sessionId,
            'aex' => $absoluteExpiresAt,
        ]);

        $response->headers->setCookie($this->cookies->access($renewed));

        return $response;
    }

    private function responseManagesAccessCookie(Response $response): bool
    {
        $name = $this->cookies->accessName();

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    /** Потолок для legacy-токенов без claim `aex` (graceful-миграция). */
    private function fallbackAbsoluteExpiry(int $now): int
    {
        return $now + (int) config('jwt.refresh_family_ttl', 90 * 24 * 60 * 60);
    }
}
