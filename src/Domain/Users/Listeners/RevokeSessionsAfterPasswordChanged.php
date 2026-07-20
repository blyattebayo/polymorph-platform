<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Listeners;

use Polymorph\Platform\Domain\Users\Core\Contracts\UserSessionRevoker;
use Polymorph\Platform\Domain\Users\Events\PasswordChanged;

final class RevokeSessionsAfterPasswordChanged
{
    public function __construct(
        private readonly UserSessionRevoker $sessionRevoker,
    ) {
    }

    public function handle(PasswordChanged $event): void
    {
        $this->sessionRevoker->revokeAllForUser((int) $event->user->id);
    }
}
