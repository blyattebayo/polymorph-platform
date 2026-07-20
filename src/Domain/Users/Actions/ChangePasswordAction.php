<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Actions;

use Polymorph\Platform\Domain\Users\Core\Contracts\UserRepository;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Domain\Users\Core\ValueObjects\Password;

/**
 * Action для смены пароля пользователя.
 */
final readonly class ChangePasswordAction
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    /**
     * Сменить пароль пользователя.
     *
     * @param  string  $newPassword  Новый пароль в открытом виде
     */
    public function execute(User $user, string $newPassword): User
    {
        // Валидация нового пароля (хеширование выполняет Eloquent cast password=hashed)
        Password::fromPlain($newPassword);

        return $this->userRepository->update($user, [
            'password' => $newPassword,
        ]);
    }
}
