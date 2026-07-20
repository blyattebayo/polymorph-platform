<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Controllers;

use Polymorph\Platform\Domain\Auth\Application\Support\AuthenticatedCredentialResolver;
use Polymorph\Platform\Domain\Auth\Application\UseCases\ListOwnAuthSessions;
use Polymorph\Platform\Domain\Auth\Application\UseCases\RevokeOwnAuthSession;
use Polymorph\Platform\Domain\Auth\Http\Resources\AuthSessionResource;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\SharedKernel\Identity\CurrentActorResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class MeAuthSessionController
{
    public function __construct(
        private CurrentActorResolver $currentActor,
        private AuthenticatedCredentialResolver $credentialResolver,
        private ListOwnAuthSessions $listOwn,
        private RevokeOwnAuthSession $revokeOwn,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->currentActor->requireUser();
        $credential = $this->credentialResolver->fromRequest($request);

        $sessions = $this->listOwn->execute($user->userId(), $credential?->sessionId);

        return AdminResponse::json([
            'data' => AuthSessionResource::collection($sessions),
        ]);
    }

    public function destroy(int $sessionId): JsonResponse
    {
        $user = $this->currentActor->requireUser();

        if (! $this->revokeOwn->execute($user->userId(), $sessionId)) {
            abort(404);
        }

        return AdminResponse::json([
            'data' => ['revoked' => true],
        ]);
    }
}
