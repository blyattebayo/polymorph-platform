<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases\Session;

use Polymorph\Platform\Domain\Auth\Application\Contracts\AuthenticationAudit;
use Polymorph\Platform\Domain\Auth\Application\Contracts\SessionRepository;
use Polymorph\Platform\Domain\Auth\Application\Contracts\TransactionManager;
use Polymorph\Platform\Domain\Auth\Application\Services\Session\RevokeUserSessions;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\SessionId;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;

final readonly class Logout
{
    public function __construct(
        private SessionRepository $sessions,
        private RevokeUserSessions $revokeUserSessions,
        private TransactionManager $transactions,
        private AuthenticationAudit $audit,
    ) {}

    public function execute(UserId $actorId, SessionId $sessionId, bool $allDevices): void
    {
        $this->transactions->run(function () use ($actorId, $sessionId, $allDevices): void {
            if ($allDevices) {
                $this->revokeUserSessions->execute($actorId);

                return;
            }

            $session = $this->sessions->findForUpdate($sessionId);
            if ($session !== null && $session->userId()->equals($actorId)) {
                $this->sessions->delete($sessionId);
            }
        });

        $this->audit->loggedOut($actorId, $allDevices);
    }
}
