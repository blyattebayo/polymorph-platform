<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Identity;

interface UserIdentity
{
    public function userId(): int;

    public function isActiveAccount(): bool;
}
