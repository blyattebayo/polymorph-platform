<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\SdkBridge;

use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\SharedKernel\Access\AccessGate;
use Polymorph\Platform\SharedKernel\Access\ResourceRef;
use Polymorph\Sdk\Access\CapabilityAction;
use Polymorph\Sdk\Errors\ExtensionError;
use Polymorph\Sdk\Identity\CurrentUser;
use Polymorph\Sdk\Identity\User;

final readonly class SdkCurrentUser implements CurrentUser
{
    public function __construct(
        private AuthenticationContext $auth,
        private AccessGate $gate,
    ) {}

    public function user(): ?User
    {
        $user = $this->auth->user();

        return $user === null ? null : UserMapper::toSdk($user);
    }

    public function requireUser(): User
    {
        return $this->user() ?? throw ExtensionError::unauthorized('Authentication required.');
    }

    public function id(): ?int
    {
        return $this->auth->userId();
    }

    public function requireId(): int
    {
        return $this->id() ?? throw ExtensionError::unauthorized('Authentication required.');
    }

    public function can(string $resource, string $action = CapabilityAction::ACCESS): bool
    {
        return $this->gate->currentUserAllows(ResourceRef::fromString($resource), trim($action));
    }
}
