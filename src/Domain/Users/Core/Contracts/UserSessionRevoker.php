<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Contracts;

interface UserSessionRevoker
{
    public function revokeAllForUser(int $userId): void;
}