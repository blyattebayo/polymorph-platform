<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Auth\Application\UseCases\ListOwnAuthSessions;
use Polymorph\Platform\Domain\Auth\Application\UseCases\RevokeOwnAuthSession;
use Polymorph\Platform\Domain\Auth\Http\Resources\AuthSessionResource;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;

final readonly class MeAuthSessionController
{
    public function __construct(
        private AuthenticationContext $auth,
        private ListOwnAuthSessions $listOwn,
        private RevokeOwnAuthSession $revokeOwn,
    ) {}

    public function index(): JsonResponse
    {
        $user = $this->auth->requireUser();

        $sessions = $this->listOwn->execute($user->userId(), $this->auth->credential()?->sessionId);

        return AdminResponse::json([
            'data' => AuthSessionResource::collection($sessions),
        ]);
    }

    public function destroy(int $sessionId): JsonResponse
    {
        $user = $this->auth->requireUser();

        if (! $this->revokeOwn->execute($user->userId(), $sessionId)) {
            abort(404);
        }

        return AdminResponse::json([
            'data' => ['revoked' => true],
        ]);
    }
}
