<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Actions;

use Polymorph\Platform\Domain\Users\Core\Contracts\UserRepository;
use Polymorph\Platform\Domain\Users\Core\Exceptions\UserAlreadyExistsException;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Domain\Users\Core\ValueObjects\Email;

/**
 * Action для обновления данных пользователя.
 */
final readonly class UpdateUserAction
{
    public function __construct(
        private UserRepository $userRepository
    ) {
    }

    /**
     * Обновить данные пользователя.
     *
     * @param array{
     *     name?: string,
     *     email?: string,
     *     status?: string,
     *     email_verified_at?: \DateTimeInterface|null
     * } $data
     * @throws UserAlreadyExistsException
     */
    public function execute(User $user, array $data): User
    {
        $data = array_intersect_key($data, array_flip(['name', 'email', 'status', 'email_verified_at']));

        if ($data === []) {
            return $user;
        }

        // Если обновляется email, проверить уникальность
        if (isset($data['email'])) {
            $newEmail = Email::fromString($data['email']);
            
            // Проверить, что новый email не используется другим пользователем
            if ($newEmail->toString() !== $user->email) {
                if ($this->userRepository->existsByEmail($newEmail->toString())) {
                    throw UserAlreadyExistsException::withEmail($newEmail->toString());
                }
            }
            
            $data['email'] = $newEmail->toString();
        }

        return $this->userRepository->update($user, $data);
    }
}
