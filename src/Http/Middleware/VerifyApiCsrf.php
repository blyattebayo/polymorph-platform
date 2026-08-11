<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\HttpErrorException;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSRF protection for API mutations. Machine-to-machine routes must opt out explicitly
 * through their route definition; credentials never disable this middleware implicitly.
 */
final class VerifyApiCsrf
{
    public function __construct(
        private readonly ErrorFactory $errorFactory,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Skip idempotent methods (GET, HEAD, OPTIONS)
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        if (! $this->isTrustedBrowserOrigin($request)) {
            $payload = $this->errorFactory->for(ErrorCode::CSRF_TOKEN_MISMATCH)->build();

            throw new HttpErrorException($payload);
        }

        return $next($request);
    }

    private function isTrustedBrowserOrigin(Request $request): bool
    {
        $secFetchSite = strtolower(trim((string) $request->header('Sec-Fetch-Site', '')));
        if ($secFetchSite === 'same-origin') {
            return true;
        }

        $origin = trim((string) $request->header('Origin', ''));
        if ($origin !== '') {
            return $this->isAllowedOrigin($origin);
        }

        $referer = trim((string) $request->header('Referer', ''));
        if ($referer !== '') {
            return $this->isAllowedOrigin($referer);
        }

        return false;
    }

    private function isAllowedOrigin(string $value): bool
    {
        $origin = parse_url($value, PHP_URL_SCHEME).'://'.parse_url($value, PHP_URL_HOST);
        $port = parse_url($value, PHP_URL_PORT);
        if (is_int($port)) {
            $origin .= ':'.$port;
        }

        $allowedOrigins = (array) config('security.csrf.allowed_origins', []);

        return in_array($origin, array_map('strval', $allowedOrigins), true);
    }
}
