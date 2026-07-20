<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Queries;

use Polymorph\Platform\Domain\Users\Core\Contracts\UserRepository;
use Polymorph\Platform\Domain\Users\Core\Exceptions\UserNotFoundException;
use Polymorph\Platform\Domain\Users\Core\Models\User;

/**
 * Query для поиска пользователя по ID.
 *
 * Часть CQRS паттерна - операция только чтения.
 */
final readonly class FindUserByIdQuery
{
    public function __construct(
        private UserRepository $repository
    ) {}

    /**
     * Найти пользователя по ID.
     *
     * @param  int  $id  ID пользователя
     * @return User|null Найденный пользователь или null
     */
    public function execute(int $id): ?User
    {
        return $this->repository->find($id);
    }

    /**
     * Найти пользователя по ID или выбросить исключение.
     *
     * @param  int  $id  ID пользователя
     * @return User Найденный пользователь
     *
     * @throws UserNotFoundException Если пользователь не найден
     */
    public function executeOrFail(int $id): User
    {
        return $this->repository->findOrFail($id);
    }
}
