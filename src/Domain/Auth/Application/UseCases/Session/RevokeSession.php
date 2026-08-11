<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases\Session;

use Polymorph\Platform\Domain\Auth\Application\Contracts\SessionRepository;
use Polymorph\Platform\Domain\Auth\Application\Contracts\TransactionManager;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\SessionId;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;

final readonly class RevokeSession
{
    public function __construct(
        private SessionRepository $sessions,
        private TransactionManager $transactions,
    ) {}

    public function execute(UserId $actor, SessionId $sessionId): bool
    {
        return $this->transactions->run(function () use ($actor, $sessionId): bool {
            $session = $this->sessions->findForUpdate($sessionId);

            if ($session === null || ! $session->userId()->equals($actor)) {
                return false;
            }

            $this->sessions->delete($sessionId);

            return true;
        });
    }
}
