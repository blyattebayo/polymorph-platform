<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Infrastructure\Repositories;

use Polymorph\Platform\Domain\Users\Core\Contracts\UserRepository;
use Polymorph\Platform\Domain\Users\Core\Exceptions\UserNotFoundException;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Eloquent реализация репозитория пользователей.
 */
final class EloquentUserRepository implements UserRepository
{
    /**
     * Найти пользователя по ID.
     */
    public function find(int $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return null;
        }

        return User::query()->where('email', $normalized)->first();
    }

    /**
     * Найти пользователя по ID или выбросить исключение.
     */
    public function findOrFail(int $id): User
    {
        $user = $this->find($id);

        if ($user === null) {
            throw UserNotFoundException::byId($id);
        }

        return $user;
    }

    /**
     * Создать нового пользователя.
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * Обновить данные пользователя.
     */
    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user->fresh();
    }

    public function paginate(PageRequest $pagination, ?string $search = null): LengthAwarePaginator
    {
        $query = User::query();

        if ($search !== null && $search !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);
            $query->where(static function ($builder) use ($escaped): void {
                $builder->where('name', 'like', '%' . $escaped . '%')
                    ->orWhere('email', 'like', '%' . $escaped . '%');
            });
        }

        return $query
            ->orderBy('created_at', 'desc')
            ->paginate(perPage: $pagination->perPage, page: $pagination->page);
    }

    /**
     * Проверить существование пользователя с данным email.
     */
    public function existsByEmail(string $email): bool
    {
        return User::where('email', strtolower($email))->exists();
    }

}
