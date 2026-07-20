<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Infrastructure\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Core\Models\Assignment;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleRepository;
use Polymorph\Platform\Domain\Roles\Core\Exceptions\RoleDeletionRejectedException;
use Polymorph\Platform\Domain\Roles\Core\Exceptions\RoleNotFoundException;
use Polymorph\Platform\Domain\Roles\Core\Models\Role;
use Polymorph\Platform\Domain\Roles\Core\Models\UserRoleAssignment;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;

final class EloquentRoleRepository implements RoleRepository
{
    public function paginate(PageRequest $pagination): LengthAwarePaginator
    {
        return Role::query()
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(perPage: $pagination->perPage, page: $pagination->page);
    }

    public function create(string $code, string $name): Role
    {
        return Role::query()->create(['code' => $code, 'name' => $name]);
    }

    public function ensureByCode(string $code, string $name, ?string $description = null): Role
    {
        return Role::query()->updateOrCreate(
            ['code' => $code],
            ['name' => $name, 'description' => $description],
        );
    }

    public function findByCode(string $code): ?Role
    {
        return Role::query()
            ->where('code', trim($code))
            ->first();
    }

    public function codesByIds(array $roleIds): array
    {
        return Role::query()
            ->whereIn('id', $roleIds)
            ->pluck('code', 'id')
            ->all();
    }

    public function deleteOne(int $roleId): void
    {
        $role = Role::query()->find($roleId);
        if ($role === null) {
            throw RoleNotFoundException::byId($roleId);
        }

        $violation = $this->deleteViolation($role);
        if ($violation !== null) {
            throw RoleDeletionRejectedException::fromViolation($violation);
        }

        $role->delete();
    }

    public function deleteByIds(array $roleIds): void
    {
        $roles = $this->findByIds($roleIds);
        $violationsByRoleId = [];

        foreach ($roles as $role) {
            $violation = $this->deleteViolation($role);
            if ($violation !== null && isset($violation['role_code'])) {
                throw RoleDeletionRejectedException::fromViolation($violation);
            }

            if ($violation !== null) {
                $violationsByRoleId[(int) $role->id] = $violation;
            }
        }

        // Атомарно: при сбое на любой роли удаление откатывается целиком,
        // без частично применённого результата.
        DB::transaction(function () use ($roles, $violationsByRoleId): void {
            foreach ($roles as $role) {
                if (isset($violationsByRoleId[(int) $role->id])) {
                    continue;
                }
                $role->delete();
            }
        });
    }

    /**
     * @param  list<int>  $roleIds
     * @return EloquentCollection<int, Role>
     */
    private function findByIds(array $roleIds): EloquentCollection
    {
        return Role::query()
            ->whereIn('id', $roleIds)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{role_id:int,detail:string,role_code?:string}|null
     */
    private function deleteViolation(Role $role): ?array
    {
        $roleId = (int) $role->id;
        if ($this->isProtected($role)) {
            return [
                'role_id' => $roleId,
                'role_code' => (string) $role->code,
                'detail' => 'Built-in role cannot be deleted.',
            ];
        }

        if ($this->isUsedByUsers($roleId)) {
            return [
                'role_id' => $roleId,
                'detail' => 'Role is assigned to users and cannot be deleted.',
            ];
        }

        if ($this->hasAccessAssignments($role)) {
            return [
                'role_id' => $roleId,
                'detail' => 'Role is referenced by access assignments and cannot be deleted.',
            ];
        }

        return null;
    }

    private function isProtected(Role $role): bool
    {
        return in_array((string) $role->code, BuiltInRoleCatalog::protectedRoleCodes(), true);
    }

    private function isUsedByUsers(int $roleId): bool
    {
        return UserRoleAssignment::query()
            ->where('role_id', $roleId)
            ->exists();
    }

    private function hasAccessAssignments(Role $role): bool
    {
        return Assignment::query()
            ->where('subject', (string) Subject::role((string) $role->code))
            ->exists();
    }
}
