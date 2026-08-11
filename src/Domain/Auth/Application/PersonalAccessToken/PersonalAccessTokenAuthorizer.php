<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken;

use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;
use Polymorph\Platform\SharedKernel\Access\AccessGate;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;
use Polymorph\Platform\SharedKernel\Access\ResourceRef;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

final readonly class PersonalAccessTokenAuthorizer
{
    private const ADMINISTRATION_RESOURCE = 'user';

    public function __construct(private AccessGate $gate) {}

    public function requireSelfServiceActor(UserIdentity $actor): UserId
    {
        return new UserId($actor->userId());
    }

    public function requireAdministrativeReader(UserIdentity $actor): UserId
    {
        return $this->requireAdministrative($actor, CapabilityCatalog::ACTION_READ);
    }

    public function requireAdministrativeManager(UserIdentity $actor): UserId
    {
        return $this->requireAdministrative($actor, CapabilityCatalog::ACTION_MANAGE);
    }

    private function requireAdministrative(UserIdentity $actor, string $action): UserId
    {
        if (! $this->gate->allows($actor, ResourceRef::fromString(self::ADMINISTRATION_RESOURCE), $action)) {
            throw PersonalAccessTokenAccessDenied::administrativeCapabilityRequired($action);
        }

        return new UserId($actor->userId());
    }
}
