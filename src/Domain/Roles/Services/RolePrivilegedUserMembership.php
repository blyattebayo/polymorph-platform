<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Services;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleAssignmentRepository;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleRepository;
use Polymorph\Platform\Domain\Roles\Core\Models\Role;
use Polymorph\Platform\Domain\Users\Core\Contracts\PrivilegedUserMembership;

final class RolePrivilegedUserMembership implements PrivilegedUserMembership
{
    public function __construct(
        private readonly RoleRepository $roles,
        private readonly RoleAssignmentRepository $roleAssignments,
    ) {}

    public function isSystemAdministrator(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $adminRoleId = $this->resolveAdminRoleId();
        if ($adminRoleId <= 0) {
            return false;
        }

        return in_array($adminRoleId, $this->roleAssignments->roleIdsForUser($userId), true);
    }

    /**
     * Без мемоизации намеренно: сервис зарегистрирован singleton'ом, и кеш id в
     * свойстве переживал бы пересоздание роли внутри жизни процесса (queue
     * worker, Octane) — isSystemAdministrator() молча возвращал бы false и
     * снимал защиту гардов (аудит, C5). Запрос по уникальному code дешёвый.
     */
    private function resolveAdminRoleId(): int
    {
        $adminRole = $this->roles->findByCode(BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN);

        return $adminRole instanceof Role ? (int) $adminRole->id : 0;
    }
}
