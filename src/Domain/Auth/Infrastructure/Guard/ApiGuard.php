<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Guard;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\AuthenticatedCredential;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\CredentialKind;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\RequestCredentialAuthenticator;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

final class ApiGuard implements Guard
{
    private ?Authenticatable $user = null;

    private ?AuthenticatedCredential $credential = null;

    private bool $resolved = false;

    private bool $manualUser = false;

    public function __construct(
        private readonly RequestCredentialAuthenticator $credentials,
        private Request $request,
    ) {}

    public function check(): bool
    {
        return $this->user() instanceof Authenticatable;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?Authenticatable
    {
        if ($this->user instanceof Authenticatable) {
            $this->syncUserAttributes($this->user);

            return $this->user;
        }

        return $this->authenticate(required: false)?->user;
    }

    public function id(): int|string|null
    {
        $user = $this->user();

        return $user instanceof Authenticatable ? $user->getAuthIdentifier() : null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->user instanceof Authenticatable;
    }

    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;
        $this->resolved = true;
        $this->manualUser = true;
        $this->syncUserAttributes($user);

        return $this;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;

        if ($this->manualUser && $this->user instanceof Authenticatable) {
            $this->syncUserAttributes($this->user);

            return;
        }

        $this->forgetResolvedUser();
    }

    public function credential(): ?AuthenticatedCredential
    {
        return $this->credential;
    }

    public function authenticate(bool $required = false): ?AuthenticatedCredential
    {
        if ($this->resolved) {
            return $this->credential;
        }

        $this->credential = $this->credentials->authenticate($this->request, $required);
        $this->user = $this->credential?->user;
        $this->resolved = true;

        return $this->credential;
    }

    private function forgetResolvedUser(): void
    {
        $this->user = null;
        $this->credential = null;
        $this->resolved = false;
        $this->manualUser = false;
    }

    private function syncUserAttributes(Authenticatable $user): void
    {
        if ($user instanceof UserIdentity) {
            $this->request->attributes->set(UserIdentity::class, $user);
        }

        if ($user instanceof User && ! $this->credential instanceof AuthenticatedCredential) {
            $this->request->attributes->set(
                AuthenticatedCredential::REQUEST_ATTRIBUTE,
                new AuthenticatedCredential($user, CredentialKind::JwtSession),
            );
        }
    }
}
