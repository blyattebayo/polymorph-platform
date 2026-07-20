<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Notifications;

use Polymorph\Platform\Domain\Auth\Core\Contracts\EmailVerificationNotifier;
use Polymorph\Platform\Domain\Users\Core\Models\User;

final readonly class LaravelEmailVerificationNotifier implements EmailVerificationNotifier
{
    public function send(User $user): void
    {
        $user->sendEmailVerificationNotification();
    }
}
