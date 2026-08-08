<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AuditActorResolver;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

final class CurrentAuditActorResolver implements AuditActorResolver
{
    public function __construct(
        private readonly AuthenticationContext $auth,
    ) {}

    public function resolve(): string
    {
        $actor = $this->auth->actor();

        if ($actor instanceof UserIdentity) {
            return 'user:'.$actor->userId();
        }

        return 'system';
    }
}
