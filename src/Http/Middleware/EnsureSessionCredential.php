<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\AuthenticatedCredential;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ThrowsErrors;

/**
 * Эндпоинт доступен только интерактивной JWT-сессии (не PAT).
 *
 * Fail-closed: отсутствие credential-атрибута — отказ, а не пропуск. Раньше
 * проверка срабатывала только при «атрибут есть И это PAT», то есть корректность
 * молча держалась на том, что auth:api стоит в цепочке раньше и атрибут
 * проставил; забытая пара middleware превращала фильтр в no-op.
 */
final class EnsureSessionCredential
{
    use ThrowsErrors;

    public const ALIAS = 'session.credential';

    public function handle(Request $request, Closure $next)
    {
        $credential = $request->attributes->get(AuthenticatedCredential::REQUEST_ATTRIBUTE);

        if (! $credential instanceof AuthenticatedCredential) {
            $this->unauthorized('Authentication is required to access this resource.');
        }

        if (! $credential->isJwtSession()) {
            $this->throwError(ErrorCode::FORBIDDEN, 'This endpoint requires an interactive session.');
        }

        return $next($request);
    }
}
