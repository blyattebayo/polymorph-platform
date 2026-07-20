<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\SdkBridge;

use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Sdk\Errors\ExtensionError;
use Polymorph\Sdk\Identity\Actor;
use Polymorph\Sdk\Identity\ActorDirectory;

/**
 * Host-адаптер {@see ActorDirectory}. Отличие от V1: requireById бросает
 * {@see ExtensionError} (свой тип SDK), а не Symfony NotFoundHttpException.
 */
final class SdkActorDirectory implements ActorDirectory
{
    public function findById(int $userId): ?Actor
    {
        $user = User::query()->find($userId);

        return $user instanceof User ? ActorMapper::fromUser($user) : null;
    }

    public function requireById(int $userId): Actor
    {
        return $this->findById($userId)
            ?? throw ExtensionError::notFound('User not found.', ['user_id' => $userId]);
    }

    public function exists(int $userId): bool
    {
        return User::query()->whereKey($userId)->exists();
    }
}
