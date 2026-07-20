<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Events;

use Polymorph\Platform\Domain\Users\Core\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: данные пользователя обновлены.
 *
 * Отправляется после успешного обновления данных пользователя.
 * Используется для логирования, синхронизации с внешними системами.
 *
 * @package Polymorph\Platform\Domain\Users\Events
 */
final class UserUpdated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param User $user Обновленный пользователь
     * @param array<string, mixed> $changes Изменённые поля
     */
    public function __construct(
        public readonly User $user,
        public readonly array $changes,
    ) {
    }
}
