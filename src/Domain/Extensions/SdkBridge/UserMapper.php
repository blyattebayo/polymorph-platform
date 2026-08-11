<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\SdkBridge;

use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Sdk\Identity\User as SdkUser;

final class UserMapper
{
    private function __construct() {}

    public static function toSdk(User $user): SdkUser
    {
        return new SdkUser(
            id: (int) $user->id,
            email: (string) $user->email,
            name: isset($user->name) ? (string) $user->name : null,
        );
    }
}
