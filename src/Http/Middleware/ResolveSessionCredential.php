<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\Domain\Auth\Infrastructure\Authentication\SessionCredentialAuthenticator;

final readonly class ResolveSessionCredential
{
    public const ALIAS = 'session.optional';

    public function __construct(
        private AuthenticationContext $context,
        private SessionCredentialAuthenticator $resolver,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $this->context->resolve($request, $this->resolver);

        return $next($request);
    }
}
