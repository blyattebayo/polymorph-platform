<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Services\Session;

use Polymorph\Platform\Domain\Auth\Application\Contracts\AuthenticationLock;
use Polymorph\Platform\Domain\Auth\Application\Contracts\SessionRepository;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;

final readonly class RevokeUserSessions
{
    public function __construct(
        private SessionRepository $sessions,
        private AuthenticationLock $authenticationLock,
    ) {}

    public function execute(UserId $userId): void
    {
        $this->authenticationLock->forUser($userId);
        $this->sessions->deleteForUser($userId);
    }
}
