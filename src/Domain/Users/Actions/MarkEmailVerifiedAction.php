<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Actions;

use Polymorph\Platform\Domain\Users\Core\Contracts\UserRepository;
use Polymorph\Platform\Domain\Users\Core\Models\User;

/**
 * Action для подтверждения email пользователя.
 */
final readonly class MarkEmailVerifiedAction
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function execute(User $user): User
    {
        return $this->userRepository->update($user, [
            'email_verified_at' => now(),
        ]);
    }
}
