<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Пароль аккаунта изменён')
            ->greeting('Здравствуйте!')
            ->line('Мы зафиксировали изменение пароля вашего аккаунта.')
            ->line('Если это были не вы, срочно смените пароль и обратитесь к администратору безопасности.');
    }
}
