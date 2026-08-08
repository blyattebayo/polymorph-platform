<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Polymorph\Platform\SharedKernel\Access\AccessGate;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;
use Polymorph\Platform\SharedKernel\Access\ResourceRef;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ThrowsErrors;

final class RequireCapability
{
    use ThrowsErrors;

    public const ALIAS = CapabilityCatalog::MIDDLEWARE_ALIAS;

    public static function forRoute(
        string $resource,
        string $action = CapabilityCatalog::ACTION_ACCESS,
    ): string {
        return CapabilityCatalog::requirement($resource, $action);
    }

    public function __construct(
        private readonly AccessGate $gate,
        private readonly AuthenticationContext $auth,
    ) {}

    public function handle(Request $request, Closure $next, string $resource, string $action = CapabilityCatalog::ACTION_ACCESS)
    {
        // Актор резолвится здесь, а не внутри гейта: middleware различает
        // 401 (не аутентифицирован) и 403 (нет права), гейт отвечает только bool.
        $user = $this->auth->actor();

        if (! $user instanceof UserIdentity) {
            $this->throwError(ErrorCode::UNAUTHORIZED, 'Authentication is required.');
        }

        $normalizedResource = trim($resource);
        $normalizedAction = trim($action);

        if (! $this->gate->allows($user, ResourceRef::fromString($normalizedResource), $normalizedAction)) {
            $this->throwError(ErrorCode::FORBIDDEN, 'Required capability is missing.', [
                'capability' => $normalizedResource.'/'.$normalizedAction,
                'resource' => $normalizedResource,
                'action' => $normalizedAction,
            ]);
        }

        return $next($request);
    }
}
