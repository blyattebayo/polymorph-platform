<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Listeners;

use Polymorph\Platform\Domain\Auth\Events\UserLoggedIn;
use Polymorph\Platform\Domain\Auth\Events\UserLoggedOut;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Слушатель для логирования событий аутентификации.
 *
 * Логирует вход и выход.
 */
final class LogAuthEvent
{
    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    /**
     * Обработать событие входа пользователя.
     */
    public function handleUserLoggedIn(UserLoggedIn $event): void
    {
        $user = $event->user;

        $this->logger->event('auth.user_logged_in', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $event->ip,
            'user_agent' => $event->userAgent,
        ]);
    }

    /**
     * Обработать событие выхода пользователя.
     */
    public function handleUserLoggedOut(UserLoggedOut $event): void
    {
        $user = $event->user;

        $this->logger->event('auth.user_logged_out', [
            'user_id' => $user->id,
            'email' => $user->email,
            'all_devices' => $event->allDevices,
        ]);
    }
}
