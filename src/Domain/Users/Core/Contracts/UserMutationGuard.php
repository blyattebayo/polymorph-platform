<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Contracts;

use Polymorph\Platform\Domain\Users\Core\Models\User;

interface UserMutationGuard
{
    public function assertCanMutate(User $user): void;
}
