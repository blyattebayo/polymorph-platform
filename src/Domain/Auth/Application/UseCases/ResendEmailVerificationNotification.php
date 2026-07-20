<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases;

use Polymorph\Platform\Domain\Auth\Application\DTO\ResendEmailVerificationNotificationCommand;
use Polymorph\Platform\Domain\Auth\Core\Contracts\EmailVerificationNotifier;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserRepository;

final readonly class ResendEmailVerificationNotification
{
    public function __construct(
        private UserRepository $users,
        private EmailVerificationNotifier $notifier,
    ) {}

    public function execute(ResendEmailVerificationNotificationCommand $command): void
    {
        $user = $this->users->find($command->userId);
        if ($user === null || $user->hasVerifiedEmail()) {
            return;
        }

        $this->notifier->send($user);
    }
}
