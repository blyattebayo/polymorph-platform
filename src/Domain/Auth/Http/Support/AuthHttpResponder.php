<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Support;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\AccessControl\Services\EffectiveCapabilityResolver;
use Polymorph\Platform\Domain\Auth\Application\Models\AuthUser;
use Polymorph\Platform\Domain\Auth\Application\Models\IssuedSession;
use Polymorph\Platform\Domain\Auth\Domain\Exceptions\AuthInvariantViolation;
use Polymorph\Platform\Domain\Auth\Infrastructure\Http\SessionCookie;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthHttpResponder
{
    public function __construct(
        private EffectiveCapabilityResolver $capabilities,
        private SessionCookie $cookie,
    ) {}

    public function authenticated(IssuedSession $result): JsonResponse
    {
        $response = AdminResponse::json($this->userPayload($this->eloquentUser($result->user)));
        $response->headers->setCookie($this->cookie->create($result->credential));

        return $response;
    }

    public function loggedOut(): Response
    {
        $response = AdminResponse::noContent();
        $response->headers->setCookie($this->cookie->forget());

        return $response;
    }

    public function current(User $user): JsonResponse
    {
        return AdminResponse::json($this->userPayload($user));
    }

    private function eloquentUser(AuthUser $user): User
    {
        if (! $user->identity instanceof User) {
            throw new AuthInvariantViolation('Authentication requires an Eloquent user identity.');
        }

        return $user->identity;
    }

    /** @return array{id: int, email: string, name: string, capabilities: list<string>} */
    private function userPayload(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'email' => (string) $user->email,
            'name' => (string) $user->name,
            'capabilities' => $this->capabilities->for($user),
        ];
    }
}
