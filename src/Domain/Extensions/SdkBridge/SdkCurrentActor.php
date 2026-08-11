<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\SdkBridge;

use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\SharedKernel\Access\AccessGate;
use Polymorph\Platform\SharedKernel\Access\ResourceRef;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;
use Polymorph\Sdk\Access\CapabilityAction;
use Polymorph\Sdk\Errors\ExtensionError;
use Polymorph\Sdk\Identity\Actor;
use Polymorph\Sdk\Identity\CurrentActor;

/**
 * Host-адаптер {@see CurrentActor}. Отличие от V1: requireActor/requireId бросают
 * {@see ExtensionError::unauthorized()} вместо Laravel abort(401)/Symfony.
 */
final class SdkCurrentActor implements CurrentActor
{
    public function __construct(
        private readonly AuthenticationContext $auth,
        private readonly AccessGate $gate,
    ) {}

    public function actor(): ?Actor
    {
        $user = $this->auth->actor();

        return $user instanceof User ? ActorMapper::fromUser($user) : null;
    }

    public function requireActor(): Actor
    {
        return $this->actor() ?? throw ExtensionError::unauthorized('Authentication required.');
    }

    public function id(): ?int
    {
        $identifier = $this->auth->authIdentifier();

        if (is_int($identifier)) {
            return $identifier;
        }

        if (is_string($identifier) && ctype_digit($identifier)) {
            return (int) $identifier;
        }

        return null;
    }

    public function requireId(): int
    {
        return $this->id() ?? throw ExtensionError::unauthorized('Authentication required.');
    }

    public function can(string $resource, string $action = CapabilityAction::ACCESS): bool
    {
        return $this->gate->currentActorAllows(ResourceRef::fromString($resource), trim($action));
    }
}
