<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Services;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Users\Actions\ChangePasswordAction;
use Polymorph\Platform\Domain\Users\Actions\CreateUserAction;
use Polymorph\Platform\Domain\Users\Actions\UpdateUserAction;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserMutationGuard;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserRoleManager;
use Polymorph\Platform\Domain\Users\Core\Exceptions\UserAlreadyExistsException;
use Polymorph\Platform\Domain\Users\Core\Exceptions\UserNotFoundException;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Domain\Users\Queries\FindUserByIdQuery;
use Polymorph\Platform\Domain\Users\Support\RoleIdsNormalizer;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;

final class AdminUserManagementService
{
    public function __construct(
        private readonly CreateUserAction $createUserAction,
        private readonly UpdateUserAction $updateUserAction,
        private readonly ChangePasswordAction $changePasswordAction,
        private readonly UserRoleManager $userRoleManager,
        private readonly UserMutationGuard $mutationGuard,
        private readonly FindUserByIdQuery $findUserByIdQuery,
        private readonly AuthenticationContext $auth,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws UserAlreadyExistsException
     */
    public function create(array $validated): User
    {
        $roleIds = RoleIdsNormalizer::normalize($validated['role_ids'] ?? []);

        // Симметрично update(): создать пользователя сразу системным админом
        // может только системный админ. Раньше create() guard не звал вовсе.
        $this->userRoleManager->assertRoleChangeAllowed($this->auth->requireActor(), null, $roleIds);

        return DB::transaction(function () use ($validated, $roleIds): User {
            $user = $this->createUserAction->execute($validated);
            $this->userRoleManager->syncForUser((int) $user->id, $roleIds);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws UserAlreadyExistsException
     * @throws UserNotFoundException
     */
    public function update(int $userId, array $validated): User
    {
        $user = $this->findUserByIdQuery->executeOrFail($userId);
        $this->mutationGuard->assertCanMutate($user);

        $roleIds = array_key_exists('role_ids', $validated)
            ? RoleIdsNormalizer::normalize($validated['role_ids'])
            : null;

        if ($roleIds !== null) {
            // Диff по членству в system.admin: выдать или снять роль может
            // только действующий системный админ.
            $this->userRoleManager->assertRoleChangeAllowed($this->auth->requireActor(), $userId, $roleIds);
        }

        // Ревок сессий при смене статуса на ограничивающий выполняет
        // листенер RevokeSessionsAfterUserStatusChanged на событии UserUpdated.
        return DB::transaction(function () use ($user, $validated, $roleIds): User {
            $updated = $this->updateUserAction->execute($user, $validated);

            if ($roleIds !== null) {
                $this->userRoleManager->syncForUser((int) $updated->id, $roleIds);
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
        $this->mutationGuard->assertCanMutate($user);
        $this->changePasswordAction->execute($user, $password);
    }
}
