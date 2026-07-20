<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\SdkBridge;

use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Sdk\Identity\Actor;

/**
 * Маппинг модели пользователя ядра в нейтральный SDK-DTO {@see Actor}.
 */
final class ActorMapper
{
    private function __construct() {}

    public static function fromUser(User $user): Actor
    {
        return new Actor(
            id: (int) $user->id,
            email: (string) $user->email,
            name: isset($user->name) ? (string) $user->name : null,
        );
    }
}
