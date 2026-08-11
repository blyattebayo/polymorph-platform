<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Authentication;

use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Users\Core\Models\User;

/** Request-scoped result of the one credential resolver selected by the route. */
final class AuthenticationContext
{
    private ?AuthenticatedCredential $credential = null;

    private ?Request $resolvedFor = null;

    public function resolve(Request $request, RequestCredentialResolver $resolver): ?AuthenticatedCredential
    {
        if ($this->resolvedFor !== $request) {
            $this->credential = $resolver->authenticate($request);
            $this->resolvedFor = $request;
        }

        return $this->credential;
    }

    public function credential(): ?AuthenticatedCredential
    {
        return $this->credential;
    }

    public function user(): ?User
    {
        return $this->credential?->user;
    }

    public function requireUser(): User
    {
        $user = $this->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    public function userId(): ?int
    {
        return $this->user() === null ? null : (int) $this->user()->id;
    }

    public function hasCredential(): bool
    {
        return $this->credential instanceof AuthenticatedCredential;
    }
}
