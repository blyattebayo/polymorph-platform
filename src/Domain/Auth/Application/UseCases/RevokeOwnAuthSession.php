<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases;

use Polymorph\Platform\Domain\Auth\Core\Contracts\RefreshSessionRepository;

final readonly class RevokeOwnAuthSession
{
    public function __construct(
        private RefreshSessionRepository $sessions,
    ) {}

    public function execute(int $userId, int $sessionId): bool
    {
        return $this->sessions->revokeForUser($userId, $sessionId);
    }
}
