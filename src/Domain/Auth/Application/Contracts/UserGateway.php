<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Contracts;

use Polymorph\Platform\Domain\Auth\Application\Models\AuthUser;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;

interface UserGateway
{
    public function findByEmail(string $email): ?AuthUser;

    public function findById(UserId $id): ?AuthUser;
}
