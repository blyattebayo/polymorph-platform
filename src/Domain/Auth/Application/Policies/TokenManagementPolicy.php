<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Policies;

use Polymorph\Platform\Domain\Auth\Application\Exceptions\TokenManagementDeniedException;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\AuthenticatedCredential;

final class TokenManagementPolicy
{
    public function canManageTokens(?AuthenticatedCredential $credential): bool
    {
        // Тот же предикат, что у middleware session.credential: правило
        // «только интерактивная сессия» живёт на credential, не в сравнениях.
        return $credential instanceof AuthenticatedCredential && $credential->isJwtSession();
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
