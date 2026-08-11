<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Application\Exceptions\AuthenticationDenied;
use Polymorph\Platform\Domain\Auth\Infrastructure\Authentication\OAuthAccessTokenCredentialAuthenticator;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\HttpErrorException;

final readonly class AuthenticateOAuthResource
{
    public const ALIAS = 'oauth.resource';

    public function __construct(
        private AuthenticationContext $context,
        private OAuthAccessTokenCredentialAuthenticator $authenticator,
        private ErrorFactory $errors,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $credential = $this->context->resolve($request, $this->authenticator);
        } catch (AuthenticationDenied) {
            $this->deny();
        }
        if ($credential === null) {
            $this->deny();
        }

        return $next($request);
    }

    private function deny(): never
    {
        throw new HttpErrorException($this->errors->for(ErrorCode::UNAUTHORIZED)
            ->detail('A valid OAuth access token for this MCP resource is required.')
            ->meta(['www_authenticate' => $this->authenticator->challenge()])
            ->build());
    }
}
