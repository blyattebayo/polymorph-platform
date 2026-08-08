<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Services;

use Polymorph\Platform\Domain\Users\Core\Contracts\PrivilegedUserMembership;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserMutationGuard;
use Polymorph\Platform\Domain\Users\Core\Exceptions\PlatformAdminImmutableException;
use Polymorph\Platform\Domain\Users\Core\Exceptions\SystemAdminPrivilegeRequiredException;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

/**
 * Два правила мутации пользователей (заменил RoleBasedUserMutationGuard):
 *
 * 1. Встроенный платформенный администратор (is_platform_admin) неприкасаем
 *    для всех — включая других системных админов.
 * 2. Обычного системного админа может мутировать только другой системный админ:
 *    users.manager не должен уметь «разжаловать» или заблокировать вышестоящего.
 *
 * Прежний guard запрещал мутацию ЛЮБОГО носителя роли system.admin кому угодно,
 * из-за чего выдача роли была необратимой: снять её мог только прямой SQL.
 */
final class PlatformAdminMutationGuard implements UserMutationGuard
{
    public function __construct(
        private readonly PrivilegedUserMembership $privilegedUserMembership,
        private readonly AuthenticationContext $auth,
    ) {}

    public function assertCanMutate(User $user): void
    {
        if ($user->isPlatformAdmin()) {
            throw PlatformAdminImmutableException::forUser($user->userId());
        }

        if (! $this->privilegedUserMembership->isSystemAdministrator($user->userId())) {
            return;
        }

        $actor = $this->auth->actor();

        // Fail-closed: нет актора — нет и права трогать системного админа.
        if ($actor instanceof UserIdentity && $this->privilegedUserMembership->isSystemAdministrator($actor->userId())) {
            return;
        }

        throw SystemAdminPrivilegeRequiredException::toModifyAdministrator($user->userId());
    }
}
