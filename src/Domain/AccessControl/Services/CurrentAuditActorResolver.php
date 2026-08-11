<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AuditActorResolver;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\Domain\Users\Core\Models\User;

final class CurrentAuditActorResolver implements AuditActorResolver
{
    public function __construct(
        private readonly AuthenticationContext $auth,
    ) {}

    public function resolve(): string
    {
        $actor = $this->auth->user();

        if ($actor instanceof User) {
            return 'user:'.(int) $actor->id;
        }

        return 'system';
    }
}
