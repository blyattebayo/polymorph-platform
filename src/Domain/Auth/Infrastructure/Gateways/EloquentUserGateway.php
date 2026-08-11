<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Gateways;

use Polymorph\Platform\Domain\Auth\Application\Contracts\UserGateway;
use Polymorph\Platform\Domain\Auth\Application\Models\AuthUser;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserRepository;
use Polymorph\Platform\Domain\Users\Core\Models\User;

final readonly class EloquentUserGateway implements UserGateway
{
    public function __construct(private UserRepository $users) {}

    public function findByEmail(string $email): ?AuthUser
    {
        return $this->map($this->users->findByEmail($email));
    }

    public function findById(UserId $id): ?AuthUser
    {
        return $this->map($this->users->find($id->value));
    }

    private function map(?User $user): ?AuthUser
    {
        if (! $user instanceof User) {
            return null;
        }

        return new AuthUser(
            identity: $user,
            passwordHash: (string) $user->password,
        );
    }
}
