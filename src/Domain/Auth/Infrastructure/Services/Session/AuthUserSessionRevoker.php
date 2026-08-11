<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services\Session;

use Polymorph\Platform\Domain\Auth\Application\Contracts\TransactionManager;
use Polymorph\Platform\Domain\Auth\Application\Services\Session\RevokeUserSessions;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserSessionRevoker;

final readonly class AuthUserSessionRevoker implements UserSessionRevoker
{
    public function __construct(
        private RevokeUserSessions $revokeUserSessions,
        private TransactionManager $transactions,
    ) {}

    public function afterPasswordChange(int $userId): void
    {
        $this->revoke($userId);
    }

    public function afterAccountRestriction(int $userId): void
    {
        $this->revoke($userId);
    }

    private function revoke(int $userId): void
    {
        $this->transactions->run(fn () => $this->revokeUserSessions->execute(new UserId($userId)));
    }
}
