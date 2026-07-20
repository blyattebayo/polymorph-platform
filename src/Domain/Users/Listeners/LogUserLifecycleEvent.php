<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Listeners;

use Polymorph\Platform\Domain\Users\Events\PasswordChanged;
use Polymorph\Platform\Domain\Users\Events\UserCreated;
use Polymorph\Platform\Domain\Users\Events\UserUpdated;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

final class LogUserLifecycleEvent
{
    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    public function handleUserCreated(UserCreated $event): void
    {
        $user = $event->user;

        $this->logger->event('users.created', [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ]);
    }

    public function handleUserUpdated(UserUpdated $event): void
    {
        $user = $event->user;

        $this->logger->event('users.updated', [
            'user_id' => $user->id,
            'email' => $user->email,
            'changes' => $event->changes,
        ]);
    }

    public function handlePasswordChanged(PasswordChanged $event): void
    {
        $user = $event->user;

        $this->logger->event('users.password_changed', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }
}
