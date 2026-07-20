<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\SdkBridge;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessSubjectProvider;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyRuntime;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\SharedKernel\Identity\CurrentActorResolver;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;
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
        private readonly CurrentActorResolver $actors,
        private readonly PolicyRuntime $policyRuntime,
        private readonly AccessSubjectProvider $subjectProvider,
    ) {
    }

    public function actor(): ?Actor
    {
        $user = $this->actors->user();

        return $user instanceof User ? ActorMapper::fromUser($user) : null;
    }

    public function requireActor(): Actor
    {
        return $this->actor() ?? throw ExtensionError::unauthorized('Authentication required.');
    }

    public function id(): ?int
    {
        $identifier = $this->actors->authIdentifier();

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
        $actor = $this->actors->actor();

        if (! $actor instanceof UserIdentity) {
            return false;
        }

        return $this->policyRuntime->allows(
            $this->subjectProvider->for($actor),
            trim($resource),
            trim($action),
        );
    }
}
