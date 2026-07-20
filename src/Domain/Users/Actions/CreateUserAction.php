<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Actions;

use Polymorph\Platform\Domain\Users\Core\Contracts\UserRepository;
use Polymorph\Platform\Domain\Users\Core\Exceptions\UserAlreadyExistsException;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Domain\Users\Core\ValueObjects\Email;
use Polymorph\Platform\Domain\Users\Core\ValueObjects\Password;

/**
 * Action для создания нового пользователя.
 */
final readonly class CreateUserAction
{
    public function __construct(
        private UserRepository $userRepository
    ) {
    }

    /**
     * Создать нового пользователя.
     *
     * @param array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     status?: string,
     *     email_verified_at?: \DateTimeInterface|null
     * } $data
     * @throws UserAlreadyExistsException
     */
    public function execute(array $data): User
    {
        // Валидация и нормализация email
        $email = Email::fromString($data['email']);

        // Проверка уникальности email
        if ($this->userRepository->existsByEmail($email->toString())) {
            throw UserAlreadyExistsException::withEmail($email->toString());
        }

        // Валидация пароля (хеширование выполняет Eloquent cast password=hashed)
        Password::fromPlain($data['password']);

        // Подготовка данных для создания
        $userData = [
            'name' => $data['name'] ?? '',
            'email' => $email->toString(),
            'password' => $data['password'],
            'status' => $data['status'] ?? User::STATUS_ACTIVE,
            'email_verified_at' => $data['email_verified_at'] ?? null,
        ];

        return $this->userRepository->create($userData);
    }
}
