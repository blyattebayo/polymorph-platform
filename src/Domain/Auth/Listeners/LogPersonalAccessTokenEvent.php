<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Listeners;

use Polymorph\Platform\Domain\Auth\Events\PersonalAccessTokenCreated;
use Polymorph\Platform\Domain\Auth\Events\PersonalAccessTokenRevoked;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

final class LogPersonalAccessTokenEvent
{
    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    public function handleCreated(PersonalAccessTokenCreated $event): void
    {
        $this->logger->event('auth.personal_access_token.created', [
            'token_id' => $event->tokenId,
            'user_id' => $event->userId,
            'created_by_user_id' => $event->createdByUserId,
        ]);
    }

    public function handleRevoked(PersonalAccessTokenRevoked $event): void
    {
        $this->logger->event('auth.personal_access_token.revoked', [
            'token_id' => $event->tokenId,
            'user_id' => $event->userId,
        ]);
    }
}
