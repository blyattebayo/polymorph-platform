<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Services;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\Domain\Roles\Services\UserRoleService;
use Polymorph\Platform\Domain\Users\Core\Exceptions\UserAlreadyExistsException;
use Polymorph\Platform\Domain\Users\Core\Exceptions\UserNotFoundException;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Domain\Users\Core\ValueObjects\Email;
use Polymorph\Platform\Domain\Users\Core\ValueObjects\Password;
use Polymorph\Platform\Domain\Users\Infrastructure\Repositories\UserRepository;
use Polymorph\Platform\Domain\Users\Support\RoleIdsNormalizer;

final class AdminUserManagementService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserRoleService $roles,
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
        $this->auth->requireUser();
        $this->roles->assertRoleChangeAllowed(null, $roleIds);

        return DB::transaction(function () use ($validated, $roleIds): User {
            $email = Email::fromString((string) $validated['email']);
            if ($this->users->existsByEmail($email->toString())) {
                throw UserAlreadyExistsException::withEmail($email->toString());
            }
            Password::fromPlain((string) $validated['password']);
            $user = $this->users->create([
                'name' => (string) ($validated['name'] ?? ''),
                'email' => $email->toString(),
                'password' => (string) $validated['password'],
                'status' => (string) ($validated['status'] ?? User::STATUS_ACTIVE),
            ]);
            $this->roles->sync((int) $user->id, $roleIds);

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
        $user = $this->users->findOrFail($userId);
        $this->roles->assertCanMutate($user);

        $roleIds = array_key_exists('role_ids', $validated)
            ? RoleIdsNormalizer::normalize($validated['role_ids'])
            : null;

        if ($roleIds !== null) {
            // Диff по членству в system.admin: выдать или снять роль может
            // только действующий системный админ.
            $this->roles->assertRoleChangeAllowed($userId, $roleIds);
        }

        // Ревок сессий при смене статуса на ограничивающий выполняет
        // листенер RevokeSessionsAfterUserStatusChanged на событии UserUpdated.
        return DB::transaction(function () use ($user, $validated, $roleIds): User {
            $data = array_intersect_key($validated, array_flip(['name', 'email', 'status']));
            if (isset($data['email'])) {
                $email = Email::fromString((string) $data['email']);
                if ($email->toString() !== $user->email && $this->users->existsByEmail($email->toString())) {
                    throw UserAlreadyExistsException::withEmail($email->toString());
                }
                $data['email'] = $email->toString();
            }
            $updated = $data === [] ? $user : $this->users->update($user, $data);

            if ($roleIds !== null) {
                $this->roles->sync((int) $updated->id, $roleIds);
            }

            return $updated;
        });
    }

    /**
     * @throws UserNotFoundException
     */
    public function setPassword(int $userId, string $password): void
    {
        $user = $this->users->findOrFail($userId);
        $this->roles->assertCanMutate($user);
        Password::fromPlain($password);
        $this->users->update($user, ['password' => $password]);
    }
}
