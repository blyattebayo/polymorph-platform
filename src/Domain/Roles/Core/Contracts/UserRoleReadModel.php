<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Core\Contracts;

interface UserRoleReadModel
{
    /**
     * @param  list<int>  $userIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function rolesByUserIds(array $userIds): array;

    /**
     * @return list<int>
     */
    public function userIdsForRole(int $roleId): array;
}
