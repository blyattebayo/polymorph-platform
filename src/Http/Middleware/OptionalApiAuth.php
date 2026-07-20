<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Core\Exceptions\JwtConfigurationException;
use Polymorph\Platform\Domain\Auth\Infrastructure\Guard\ApiGuard;

final readonly class OptionalApiAuth
{
    public const ALIAS = 'auth.optional';

    public function __construct(
        private AuthFactory $auth,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        try {
            $guard = $this->auth->guard('api');

            if ($guard instanceof ApiGuard) {
                $guard->authenticate(required: false);
            } else {
                $guard->user();
            }
        } catch (JwtConfigurationException $exception) {
            throw $exception;
        } catch (\Throwable) {
            // Optional auth must not fail public routes because of a bad bearer/cookie.
        }

        return $next($request);
    }
}
