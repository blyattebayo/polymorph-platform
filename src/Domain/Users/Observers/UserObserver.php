<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Observers;

use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Domain\Users\Events\PasswordChanged;
use Polymorph\Platform\Domain\Users\Events\UserCreated;
use Polymorph\Platform\Domain\Users\Events\UserUpdated;
use Illuminate\Support\Facades\Event;

/**
 * Observer для модели User.
 *
 * Обрабатывает события жизненного цикла User и отправляет domain события.
 *
 * @package Polymorph\Platform\Domain\Users\Observers
 */
class UserObserver
{
    /**
     * Обработка события после создания пользователя.
     */
    public function created(User $user): void
    {
        Event::dispatch(new UserCreated($user));
    }

    /**
     * Обработка события после обновления пользователя.
     */
    public function updated(User $user): void
    {
        $changes = $user->getChanges();

        // Проверяем, изменился ли пароль
        if (isset($changes['password'])) {
            Event::dispatch(new PasswordChanged($user));
        }

        Event::dispatch(new UserUpdated($user, $changes));
    }

}
