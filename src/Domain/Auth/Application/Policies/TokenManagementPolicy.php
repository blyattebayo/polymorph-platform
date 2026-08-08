<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Policies;

use Polymorph\Platform\Domain\Auth\Application\Exceptions\TokenManagementDeniedException;
use Polymorph\Platform\SharedKernel\Identity\AuthenticatedCredential;

final class TokenManagementPolicy
{
    public function canManageTokens(?AuthenticatedCredential $credential): bool
    {
        // Тот же предикат и тот же вход, что у middleware session.credential:
        // оба берут credential из AuthenticationContext и спрашивают isSession().
        // Раньше вход отличался — middleware отказывал при отсутствии credential,
        // а сюда его подставлял резолвер, синтезируя «интерактивную сессию» из
        // одного лишь факта, что пользователь установлен.
        return $credential instanceof AuthenticatedCredential && $credential->isSession();
    }

    public function assertCanManageTokens(?AuthenticatedCredential $credential): void
    {
        if (! $this->canManageTokens($credential)) {
            throw new TokenManagementDeniedException(
                'Personal access tokens can only be managed with an interactive session.',
            );
        }
    }
}
