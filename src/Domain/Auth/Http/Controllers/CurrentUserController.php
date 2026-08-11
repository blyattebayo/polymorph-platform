<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Auth\Domain\Exceptions\AuthInvariantViolation;
use Polymorph\Platform\Domain\Auth\Http\Support\AuthHttpResponder;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;

final readonly class CurrentUserController
{
    public function __construct(
        private AuthenticationContext $auth,
        private AuthHttpResponder $responses,
    ) {}

    public function __invoke(): JsonResponse
    {
        $user = $this->auth->requireActor();

        if (! $user instanceof User) {
            throw new AuthInvariantViolation('Current user endpoint requires an Eloquent user identity.');
        }

        return $this->responses->current($user);
    }
}
