<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Listeners;

use Polymorph\Platform\Domain\Users\Events\PasswordChanged;
use Polymorph\Platform\Domain\Users\Notifications\PasswordChangedNotification;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Слушатель для отправки уведомлений о смене пароля.
 */
final class NotifyPasswordChanged
{
    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    /**
     * Обработать событие смены пароля.
     */
    public function handle(PasswordChanged $event): void
    {
        $user = $event->user;

        if (! is_string($user->email) || trim($user->email) === '') {
            $this->logger->warning('users.password_changed.notification_skipped', [
                'user_id' => $user->id,
                'reason' => 'missing_email',
            ]);

            return;
        }

        $user->notify(new PasswordChangedNotification);

        $this->logger->info('users.password_changed.notification_queued', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }
}
