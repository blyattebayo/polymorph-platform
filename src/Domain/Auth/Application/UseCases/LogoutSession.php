<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases;

use Illuminate\Support\Facades\Event;
use Polymorph\Platform\Domain\Auth\Application\DTO\LogoutSessionCommand;
use Polymorph\Platform\Domain\Auth\Core\Contracts\RefreshSessionRepository;
use Polymorph\Platform\Domain\Auth\Events\UserLoggedOut;

final readonly class LogoutSession
{
    public function __construct(
        private RefreshSessionRepository $sessions,
    ) {}

    public function execute(LogoutSessionCommand $command): void
    {
        if ($command->allDevices) {
            $this->sessions->revokeAllForUser($command->actor->userId());
        } elseif ($command->sessionId !== null) {
            $this->sessions->revokeForUser($command->actor->userId(), $command->sessionId);
        } else {
            $this->sessions->revokeByRefreshToken($command->refreshToken);
        }

        Event::dispatch(new UserLoggedOut(
            user: $command->actor,
            allDevices: $command->allDevices,
        ));
    }
}
