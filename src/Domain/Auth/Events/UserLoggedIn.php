<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Events;

use Polymorph\Platform\Domain\Users\Core\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: пользователь выполнил вход.
 *
 * Отправляется после успешной аутентификации пользователя.
 * Используется для логирования, отслеживания последнего входа, уведомлений о входе.
 *
 * @package Polymorph\Platform\Domain\Auth\Events
 */
final class UserLoggedIn
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param User $user Вошедший пользователь
     * @param string|null $ip IP-адрес
     * @param string|null $userAgent User-Agent браузера
     */
    public function __construct(
        public readonly User $user,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
    ) {
    }
}
