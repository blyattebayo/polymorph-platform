<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Core\Exceptions\JwtConfigurationException;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;

final readonly class OptionalApiAuth
{
    public const ALIAS = 'auth.optional';

    public function __construct(
        private AuthenticationContext $context,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        try {
            $this->context->credential();
        } catch (JwtConfigurationException $exception) {
            throw $exception;
        } catch (\Throwable) {
            // Optional auth must not fail public routes because of a bad bearer/cookie.
        }

        return $next($request);
    }
}
