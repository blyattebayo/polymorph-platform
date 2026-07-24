<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware для добавления заголовков Vary: Origin, Cookie к ответам с cookies.
 *
 * Обеспечивает корректное поведение кэша при наличии cookies,
 * так как ответы с cookies могут различаться в зависимости от заголовков Origin и Cookie.
 */
final class AddCacheVary
{
    /**
     * Обработать входящий запрос.
     *
     * Добавляет заголовки Vary: Origin, Cookie к ответам, которые устанавливают cookies.
     *
     * @param  Request  $request  HTTP запрос
     * @param  Closure  $next  Следующий middleware
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Add Vary headers for responses that set cookies
        if ($response->headers->has('Set-Cookie')) {
            $existingVary = $response->headers->get('Vary', '');
            $varyHeaders = array_filter(explode(',', $existingVary));
            $varyHeaders = array_map('trim', $varyHeaders);

            // Add Origin and Cookie if not already present
            if (! in_array('Origin', $varyHeaders, true)) {
                $varyHeaders[] = 'Origin';
            }
            if (! in_array('Cookie', $varyHeaders, true)) {
                $varyHeaders[] = 'Cookie';
            }

            // Use the ResponseHeaderBag directly: Response::header() only exists on
            // Illuminate\Http\Response, not on Symfony BinaryFileResponse (returned by
            // response()->file(), e.g. MediaPreviewController serving a local disk).
            // headers->set() is available on every HttpFoundation response type.
            $response->headers->set('Vary', implode(', ', $varyHeaders));
        }

        return $response;
    }
}
