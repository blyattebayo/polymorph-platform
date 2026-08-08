<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Polymorph\Platform\SharedKernel\Identity\AuthenticatedCredential;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ThrowsErrors;

/**
 * Эндпоинт доступен только интерактивной сессии (не PAT и не актору,
 * назначенному кодом через Auth::setUser).
 *
 * Fail-closed: отсутствие credential — отказ, а не пропуск. Раньше проверка
 * срабатывала только при «атрибут есть И это PAT», то есть корректность молча
 * держалась на том, что auth:api стоит в цепочке раньше и атрибут проставил;
 * забытая пара middleware превращала фильтр в no-op. Теперь credential берётся
 * из контекста, который при необходимости разберёт запрос сам.
 */
final class EnsureSessionCredential
{
    use ThrowsErrors;

    public const ALIAS = 'session.credential';

    public function __construct(
        private readonly AuthenticationContext $context,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $credential = $this->context->credential();

        if (! $credential instanceof AuthenticatedCredential) {
            $this->unauthorized('Authentication is required to access this resource.');
        }

        if (! $credential->isSession()) {
            $this->throwError(ErrorCode::FORBIDDEN, 'This endpoint requires an interactive session.');
        }

        return $next($request);
    }
}
