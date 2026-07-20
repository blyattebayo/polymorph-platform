<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Polymorph\Platform\Domain\Users\Core\Models\User;

/**
 * Событие: пользователь вышел из системы.
 *
 * Отправляется после успешного выхода пользователя (logout).
 * Используется для логирования, очистки кэша сессий, уведомлений.
 */
final class UserLoggedOut
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  User  $user  Вышедший пользователь
     * @param  bool  $allDevices  true, если выход со всех устройств
     */
    public function __construct(
        public readonly User $user,
        public readonly bool $allDevices = false,
    ) {}
}
