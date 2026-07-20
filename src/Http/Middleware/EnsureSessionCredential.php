<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\AuthenticatedCredential;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\CredentialKind;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ThrowsErrors;

final class EnsureSessionCredential
{
    use ThrowsErrors;

    public const ALIAS = 'session.credential';

    public function handle(Request $request, Closure $next)
    {
        $credential = $request->attributes->get(AuthenticatedCredential::REQUEST_ATTRIBUTE);

        if ($credential instanceof AuthenticatedCredential && $credential->kind === CredentialKind::PersonalAccessToken) {
            $this->throwError(ErrorCode::FORBIDDEN, 'This endpoint requires an interactive session.');
        }

        return $next($request);
    }
}
