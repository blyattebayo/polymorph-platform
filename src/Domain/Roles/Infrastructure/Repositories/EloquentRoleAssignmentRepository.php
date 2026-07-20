<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Infrastructure\Repositories;

use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleAssignmentRepository;
use Polymorph\Platform\Domain\Roles\Core\Models\UserRoleAssignment;

final class EloquentRoleAssignmentRepository implements RoleAssignmentRepository
{
    public function assign(int $userId, int $roleId): void
    {
        UserRoleAssignment::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'role_id' => $roleId,
            ],
            [],
        );
    }

    public function unassign(int $userId, int $roleId): void
    {
        UserRoleAssignment::query()
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->delete();
    }

    public function syncForUser(int $userId, array $roleIds): void
    {
        UserRoleAssignment::query()->where('user_id', $userId)->delete();

        if ($roleIds === []) {
            return;
        }

        $rows = [];
        $now = now();

        foreach ($roleIds as $roleId) {
            $rows[] = [
                'user_id' => $userId,
                'role_id' => $roleId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        UserRoleAssignment::query()->upsert($rows, ['user_id', 'role_id'], ['updated_at']);
    }

    public function roleIdsForUser(int $userId): array
    {
        return UserRoleAssignment::query()
            ->where('user_id', $userId)
            ->pluck('role_id')
            ->values()
            ->all();
    }

    public function roleCodesForUser(int $userId): array
    {
        return UserRoleAssignment::query()
            ->join('roles', 'roles.id', '=', 'user_role_assignments.role_id')
            ->where('user_role_assignments.user_id', $userId)
            ->orderBy('roles.id')
            ->pluck('roles.code')
            ->map(static fn (mixed $code): string => trim((string) $code))
            ->filter(static fn (string $code): bool => $code !== '')
            ->unique()
            ->values()
            ->all();
    }
}
