<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Events;

use Polymorph\Platform\Domain\Users\Core\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: пользователь создан.
 *
 * Отправляется после успешного создания нового пользователя.
 * Используется для логирования, отправки welcome email и уведомлений.
 *
 * @package Polymorph\Platform\Domain\Users\Events
 */
final class UserCreated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param User $user Созданный пользователь
     */
    public function __construct(
        public readonly User $user,
    ) {
    }
}
