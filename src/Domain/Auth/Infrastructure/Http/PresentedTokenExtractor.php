<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Http;

use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\PresentedToken;

/**
 * Достаёт предъявленный токен из запроса. Пришёл на смену трейту
 * Http\Middleware\Concerns\ExtractsJwtAccessToken, у которого было три беды:
 * он назывался Jwt, но доставал и PAT; лежал в Http\Middleware, а подмешивался
 * в сервис домена Auth (зависимость снизу вверх); и знал имя куки отдельно от
 * {@see AuthCookieFactory}, которая эту куку выписывает.
 */
final class PresentedTokenExtractor
{
    public function __construct(
        private readonly AuthCookieFactory $cookies,
    ) {}

    public function fromRequest(Request $request): ?PresentedToken
    {
        $bearer = PresentedToken::bearer((string) $request->bearerToken());
        if ($bearer instanceof PresentedToken) {
            return $bearer;
        }

        $cookieName = $this->cookies->accessName();

        $cookie = PresentedToken::cookie((string) $request->cookie($cookieName, ''));
        if ($cookie instanceof PresentedToken) {
            return $cookie;
        }

        return PresentedToken::cookie($this->fromRawCookieHeader($request, $cookieName));
    }

    /**
     * Фолбэк с разбором сырого заголовка Cookie. Живёт с первого коммита без
     * записанной причины: access-кука исключена из шифрования, поэтому
     * $request->cookie() обязан её отдавать сам. Оставлен как есть — снимать
     * страховку в security-пути имеет смысл отдельной задачей, где будет чем
     * подтвердить, что она действительно лишняя.
     */
    private function fromRawCookieHeader(Request $request, string $cookieName): string
    {
        $header = (string) $request->header('Cookie', '');
        if ($header === '') {
            return '';
        }

        if (preg_match('/'.preg_quote($cookieName, '/').'=([^;]+)/', $header, $matches) !== 1) {
            return '';
        }

        return urldecode((string) $matches[1]);
    }
}
