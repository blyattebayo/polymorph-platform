<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Core\Contracts;

interface RoleAssignmentRepository
{
    public function assign(int $userId, int $roleId): void;

    public function unassign(int $userId, int $roleId): void;

    /**
     * @param  list<int>  $roleIds
     */
    public function syncForUser(int $userId, array $roleIds): void;

    /**
     * @return list<int>
     */
    public function roleIdsForUser(int $userId): array;

    /**
     * @return list<string>
     */
    public function roleCodesForUser(int $userId): array;
}
