<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\Domain\Auth\Application\UseCases\Session\ListSessions;
use Polymorph\Platform\Domain\Auth\Application\UseCases\Session\RevokeSession;
use Polymorph\Platform\Domain\Auth\Domain\Session;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\SessionId;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;
use Polymorph\Platform\Domain\Auth\Http\Resources\AuthSessionResource;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;

final readonly class MeAuthSessionController
{
    public function __construct(
        private AuthenticationContext $auth,
        private ListSessions $listSessions,
        private RevokeSession $revokeSession,
    ) {}

    public function index(): JsonResponse
    {
        $user = $this->auth->requireUser();
        $currentSessionId = $this->auth->credential()?->sessionId();
        $sessions = $this->listSessions->execute(new UserId((int) $user->id));

        return AdminResponse::json([
            'data' => array_map(
                static fn (Session $session): array => (new AuthSessionResource(
                    $session,
                    is_string($currentSessionId) && (string) $session->id() === $currentSessionId,
                ))->resolve(),
                $sessions,
            ),
        ]);
    }

    public function destroy(string $sessionId): JsonResponse
    {
        $user = $this->auth->requireUser();

        if (! $this->revokeSession->execute(new UserId((int) $user->id), new SessionId($sessionId))) {
            abort(404);
        }

        return AdminResponse::json(['data' => ['revoked' => true]]);
    }
}
