<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Services;

use Polymorph\Platform\Domain\Users\Actions\ChangePasswordAction;
use Polymorph\Platform\Domain\Users\Actions\CreateUserAction;
use Polymorph\Platform\Domain\Users\Actions\UpdateUserAction;
use Polymorph\Platform\Domain\Users\Core\Contracts\SystemAdministratorGuard;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserRoleManager;
use Polymorph\Platform\Domain\Users\Core\Exceptions\UserAlreadyExistsException;
use Polymorph\Platform\Domain\Users\Core\Exceptions\UserNotFoundException;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Domain\Users\Queries\FindUserByIdQuery;
use Polymorph\Platform\Domain\Users\Support\RoleIdsNormalizer;
use Illuminate\Support\Facades\DB;

final class AdminUserManagementService
{
    public function __construct(
        private readonly CreateUserAction $createUserAction,
        private readonly UpdateUserAction $updateUserAction,
        private readonly ChangePasswordAction $changePasswordAction,
        private readonly UserRoleManager $userRoleManager,
        private readonly SystemAdministratorGuard $systemAdministratorGuard,
        private readonly FindUserByIdQuery $findUserByIdQuery,
    ) {
    }

    /**
     * @param array<string, mixed> $validated
     * @throws UserAlreadyExistsException
     */
    public function create(array $validated): User
    {
        $roleIds = RoleIdsNormalizer::normalize($validated['role_ids'] ?? []);
        return DB::transaction(function () use ($validated, $roleIds): User {
            $user = $this->createUserAction->execute($validated);
            $this->userRoleManager->syncForUser((int) $user->id, $roleIds);
            return $user;
        });
    }

    /**
     * @param array<string, mixed> $validated
     * @throws UserAlreadyExistsException
     * @throws UserNotFoundException
     */
    public function update(int $userId, array $validated): User
    {
        $user = $this->findUserByIdQuery->executeOrFail($userId);
        $this->systemAdministratorGuard->assertCanMutate($user);

        // Ревок сессий при смене статуса на ограничивающий выполняет
        // листенер RevokeSessionsAfterUserStatusChanged на событии UserUpdated.
        return DB::transaction(function () use ($user, $validated): User {
            $updated = $this->updateUserAction->execute($user, $validated);

            if (array_key_exists('role_ids', $validated)) {
                $this->userRoleManager->syncForUser(
                    (int) $updated->id,
                    RoleIdsNormalizer::normalize($validated['role_ids']),
                );
            }

            return $updated;
        });
    }

    /**
     * @throws UserNotFoundException
     */
    public function setPassword(int $userId, string $password): void
    {
        $user = $this->findUserByIdQuery->executeOrFail($userId);
        $this->systemAdministratorGuard->assertCanMutate($user);
        $this->changePasswordAction->execute($user, $password);
    }
}