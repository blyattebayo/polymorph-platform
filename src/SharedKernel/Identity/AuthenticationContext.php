<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Identity;

use Illuminate\Http\Request;

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

    public function actor(): ?UserIdentity
    {
        return $this->credential?->actor;
    }

    public function requireActor(): UserIdentity
    {
        $actor = $this->actor();
        abort_unless($actor instanceof UserIdentity, 401);

        return $actor;
    }

    public function authIdentifier(): ?int
    {
        return $this->actor()?->userId();
    }

    public function hasCredential(): bool
    {
        return $this->credential instanceof AuthenticatedCredential;
    }
}
