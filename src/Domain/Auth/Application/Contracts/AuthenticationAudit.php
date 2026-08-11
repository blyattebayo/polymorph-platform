<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Contracts;

use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\ClientMetadata;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;

interface AuthenticationAudit
{
    public function loggedIn(UserId $userId, ClientMetadata $client): void;

    public function loggedOut(UserId $userId, bool $allDevices): void;
}
