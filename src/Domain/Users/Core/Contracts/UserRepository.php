<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Polymorph\Platform\Domain\Users\Core\Exceptions\UserNotFoundException;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;

/**
 * Репозиторий для работы с пользователями.
 */
interface UserRepository
{
    /**
     * Найти пользователя по ID.
     */
    public function find(int $id): ?User;

    /**
     * Найти пользователя по нормализованному email.
     */
    public function findByEmail(string $email): ?User;

    /**
     * Найти пользователя по ID или выбросить исключение.
     *
     * @throws UserNotFoundException
     */
    public function findOrFail(int $id): User;

    /**
     * Создать нового пользователя.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User;

    /**
     * Обновить данные пользователя.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User;

    /**
     * Страница пользователей; $search — фильтр по подстроке имени или email.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(PageRequest $pagination, ?string $search = null): LengthAwarePaginator;

    /**
     * Проверить существование пользователя с данным email.
     */
    public function existsByEmail(string $email): bool;
}
