<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Core\Contracts;

use Polymorph\Platform\Domain\Roles\Core\Models\Role;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RoleRepository
{
    /** @return LengthAwarePaginator<int, Role> */
    public function paginate(PageRequest $pagination): LengthAwarePaginator;

    public function create(string $code, string $name): Role;

    /**
     * Создать роль по коду или обновить её атрибуты (провижининг встроенных ролей).
     */
    public function ensureByCode(string $code, string $name, ?string $description = null): Role;

    public function findByCode(string $code): ?Role;

    /**
     * @param list<int> $roleIds
     * @return array<int, string>
     */
    public function codesByIds(array $roleIds): array;

    public function deleteOne(int $roleId): void;

    /**
     * @param list<int> $roleIds
     */
    public function deleteByIds(array $roleIds): void;
}
