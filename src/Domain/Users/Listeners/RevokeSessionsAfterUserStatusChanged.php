<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Listeners;

use Polymorph\Platform\Domain\Users\Core\Contracts\UserSessionRevoker;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Domain\Users\Events\UserUpdated;

/**
 * Ревокает все refresh-сессии пользователя, когда его статус сменился
 * на ограничивающий (blocked/inactive).
 *
 * Симметричен {@see RevokeSessionsAfterPasswordChanged}: побочный эффект
 * привязан к доменному событию, а не инлайнится в сервис. Опирается на
 * UserUpdated::$changes (Eloquent getChanges()), который содержит ключ
 * 'status' только при фактической смене значения — поэтому повторная
 * установка того же статуса сессии не ревокает.
 */
final class RevokeSessionsAfterUserStatusChanged
{
    public function __construct(
        private readonly UserSessionRevoker $sessionRevoker,
    ) {}

    public function handle(UserUpdated $event): void
    {
        $status = $event->changes['status'] ?? null;

        if ($status === User::STATUS_BLOCKED || $status === User::STATUS_INACTIVE) {
            $this->sessionRevoker->revokeAllForUser((int) $event->user->id);
        }
    }
}
