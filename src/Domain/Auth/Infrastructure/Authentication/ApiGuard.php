<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Authentication;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use LogicException;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\Domain\Auth\Application\Authentication\RequestCredentialResolver;

final class ApiGuard implements Guard
{
    public function __construct(
        private Request $request,
        private readonly AuthenticationContext $context,
        private readonly RequestCredentialResolver $resolver,
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
        $actor = $this->context->resolve($this->request, $this->resolver)?->user;

        return $actor instanceof Authenticatable ? $actor : null;
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->context->hasCredential();
    }

    public function setUser(Authenticatable $user): static
    {
        throw new LogicException('ApiGuard accepts credentials from the current HTTP request only.');
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }
}
