<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware для добавления заголовка Cache-Control: no-store к auth эндпоинтам.
 *
 * Предотвращает кэширование ответов аутентификации прокси и браузерами.
 */
final class NoCacheAuth
{
    /**
     * Обработать входящий запрос.
     *
     * Добавляет заголовок Cache-Control: no-store к ответу.
     *
     * @param  Request  $request  HTTP запрос
     * @param  Closure  $next  Следующий middleware
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Add Cache-Control: no-store to prevent caching of auth responses
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }
}
