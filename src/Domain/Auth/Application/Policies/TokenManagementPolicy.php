<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Policies;

use Polymorph\Platform\Domain\Auth\Core\ValueObjects\AuthenticatedCredential;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\CredentialKind;

final class TokenManagementPolicy
{
    public function canManageTokens(?AuthenticatedCredential $credential): bool
    {
        if ($credential === null) {
            return false;
        }

        return $credential->kind === CredentialKind::JwtSession;
    }

    public function assertCanManageTokens(?AuthenticatedCredential $credential): void
    {
        if (! $this->canManageTokens($credential)) {
            throw new \Polymorph\Platform\Domain\Auth\Application\Exceptions\TokenManagementDeniedException(
                'Personal access tokens can only be managed with an interactive session.',
            );
        }
    }
}
