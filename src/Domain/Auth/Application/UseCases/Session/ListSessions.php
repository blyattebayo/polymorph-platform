<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases\Session;

use Polymorph\Platform\Domain\Auth\Application\Contracts\Clock;
use Polymorph\Platform\Domain\Auth\Application\Contracts\SessionRepository;
use Polymorph\Platform\Domain\Auth\Domain\Session;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;

final readonly class ListSessions
{
    public function __construct(
        private SessionRepository $sessions,
        private Clock $clock,
    ) {}

    /**
     * @return list<Session>
     */
    public function execute(UserId $userId): array
    {
        return $this->sessions->activeForUser($userId, $this->clock->now());
    }
}
