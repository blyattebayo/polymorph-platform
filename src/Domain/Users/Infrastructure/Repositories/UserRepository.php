<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Infrastructure\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Polymorph\Platform\Domain\Users\Core\Exceptions\UserNotFoundException;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;

final class UserRepository
{
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

    public function findOrFail(int $id): User
    {
        $user = $this->find($id);

        if ($user === null) {
            throw UserNotFoundException::byId($id);
        }

        return $user;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    /** @return LengthAwarePaginator<int, User> */
    public function paginate(PageRequest $pagination, ?string $search = null, ?int $excludeUserId = null): LengthAwarePaginator
    {
        $query = User::query();

        if ($excludeUserId !== null) {
            $query->whereKeyNot($excludeUserId);
        }

        if ($search !== null && $search !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);
            $query->where(static function ($builder) use ($escaped): void {
                $builder->where('name', 'like', '%'.$escaped.'%')
                    ->orWhere('email', 'like', '%'.$escaped.'%');
            });
        }

        return $query
            ->orderBy('created_at', 'desc')
            ->paginate(perPage: $pagination->perPage, page: $pagination->page);
    }

    public function existsByEmail(string $email): bool
    {
        return User::where('email', strtolower($email))->exists();
    }
}
