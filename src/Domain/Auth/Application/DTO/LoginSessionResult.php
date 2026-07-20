<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\DTO;

use Polymorph\Platform\Domain\Users\Core\Models\User;

final readonly class LoginSessionResult
{
    /**
     * @param list<string> $capabilities
     */
    public function __construct(
        public User $user,
        public string $accessToken,
        public string $refreshToken,
        public array $capabilities,
    ) {
    }
}
