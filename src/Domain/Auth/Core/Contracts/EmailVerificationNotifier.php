<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\Contracts;

use Polymorph\Platform\Domain\Users\Core\Models\User;

interface EmailVerificationNotifier
{
    public function send(User $user): void;
}
