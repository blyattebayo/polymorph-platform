<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Listeners;

use Polymorph\Platform\Domain\Roles\Services\AccessProvisioner;
use Polymorph\Platform\Domain\Users\Events\UserCreated;

final class SyncUserAccessOnCreated
{
    public function __construct(
        private readonly AccessProvisioner $accessProvisioner,
    ) {}

    public function handle(UserCreated $event): void
    {
        $this->accessProvisioner->syncForUser($event->user);
    }
}
