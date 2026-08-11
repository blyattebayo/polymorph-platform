<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\Data;

use Polymorph\Platform\SharedKernel\Access\CredentialScopes;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

final readonly class AuthenticatedPersonalAccessToken
{
    public function __construct(
        public UserIdentity $actor,
        public CredentialScopes $scopes,
    ) {}
}
