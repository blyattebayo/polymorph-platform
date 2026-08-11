<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\SdkBridge;

use Polymorph\Platform\Domain\Users\Core\Models\User as PlatformUser;
use Polymorph\Sdk\Errors\ExtensionError;
use Polymorph\Sdk\Identity\User;
use Polymorph\Sdk\Identity\UserDirectory;

final class SdkUserDirectory implements UserDirectory
{
    public function findById(int $userId): ?User
    {
        $user = PlatformUser::query()->find($userId);

        return $user instanceof PlatformUser ? UserMapper::toSdk($user) : null;
    }

    public function requireById(int $userId): User
    {
        return $this->findById($userId)
            ?? throw ExtensionError::notFound('User not found.', ['user_id' => $userId]);
    }

    public function exists(int $userId): bool
    {
        return PlatformUser::query()->whereKey($userId)->exists();
    }
}
