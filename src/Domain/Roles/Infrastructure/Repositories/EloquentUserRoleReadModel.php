<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Infrastructure\Repositories;

use Polymorph\Platform\Domain\Roles\Core\Contracts\UserRoleReadModel;
use Polymorph\Platform\Domain\Roles\Core\Models\UserRoleAssignment;
use Illuminate\Support\Facades\DB;

final class EloquentUserRoleReadModel implements UserRoleReadModel
{
    public function rolesByUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = DB::table('user_role_assignments as ura')
            ->join('roles as r', 'r.id', '=', 'ura.role_id')
            ->whereIn('ura.user_id', $userIds)
            ->select([
                'ura.user_id as user_id',
                'r.id as id',
                'r.code as code',
                'r.name as name',
            ])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $userId = (int) $row->user_id;
            $result[$userId] ??= [];
            $result[$userId][] = [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
            ];
        }

        return $result;
    }

    public function userIdsForRole(int $roleId): array
    {
        return UserRoleAssignment::query()
            ->where('role_id', $roleId)
            ->pluck('user_id')
            ->map(static fn (mixed $userId): int => (int) $userId)
            ->values()
            ->all();
    }
}