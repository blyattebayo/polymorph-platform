<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Contracts;

interface UserSessionRevoker
{
    public function afterPasswordChange(int $userId): void;

    public function afterAccountRestriction(int $userId): void;
}
