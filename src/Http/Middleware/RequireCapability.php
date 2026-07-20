<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Middleware;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyRuntime;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessSubjectProvider;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;
use Polymorph\Platform\SharedKernel\Identity\CurrentActorResolver;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ThrowsErrors;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class RequireCapability
{
    use ThrowsErrors;

    public const ALIAS = 'capability.require';

    public static function forRoute(
        string $resource,
        string $action = CapabilityCatalog::ACTION_ACCESS,
    ): string {
        $normalizedResource = trim($resource);
        $normalizedAction = trim($action);

        if ($normalizedResource === '' || $normalizedAction === '') {
            throw new InvalidArgumentException('Capability resource and action must not be empty.');
        }

        return self::ALIAS . ':' . $normalizedResource . ',' . $normalizedAction;
    }

    public function __construct(
        private readonly PolicyRuntime $policyRuntime,
        private readonly AccessSubjectProvider $subjectProvider,
        private readonly CurrentActorResolver $currentActor,
    ) {
    }

    public function handle(Request $request, Closure $next, string $resource, string $action = CapabilityCatalog::ACTION_ACCESS)
    {
        $user = $this->currentActor->actor();

        if (! $user instanceof UserIdentity) {
            $this->throwError(ErrorCode::UNAUTHORIZED, 'Authentication is required.');
        }

        $normalizedResource = trim($resource);
        $normalizedAction = trim($action);

        if (! $this->policyRuntime->allows($this->subjectProvider->for($user), $normalizedResource, $normalizedAction)) {
            $this->throwError(ErrorCode::FORBIDDEN, 'Required capability is missing.', [
                'capability' => $normalizedResource . '/' . $normalizedAction,
                'resource' => $normalizedResource,
                'action' => $normalizedAction,
            ]);
        }

        return $next($request);
    }
}
