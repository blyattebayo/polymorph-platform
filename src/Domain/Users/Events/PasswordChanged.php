<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Polymorph\Platform\Domain\Users\Core\Models\User;

/**
 * Событие: пароль пользователя изменен.
 *
 * Отправляется после успешной смены пароля.
 * Используется для отправки уведомлений о смене пароля, логирования.
 */
final class PasswordChanged
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  User  $user  Пользователь, у которого изменен пароль
     */
    public function __construct(
        public readonly User $user,
    ) {}
}
