<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Controllers;

use Polymorph\Platform\Domain\Auth\Application\UseCases\Session\Logout;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\SessionId;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;
use Polymorph\Platform\Domain\Auth\Http\Requests\LogoutRequest;
use Polymorph\Platform\Domain\Auth\Http\Support\AuthHttpResponder;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;
use Symfony\Component\HttpFoundation\Response;

final readonly class LogoutController
{
    public function __construct(
        private Logout $logout,
        private AuthenticationContext $auth,
        private AuthHttpResponder $responses,
    ) {}

    public function __invoke(LogoutRequest $request): Response
    {
        $actor = $this->auth->requireActor();
        $sessionId = $this->auth->credential()?->sessionId;
        abort_unless(is_string($sessionId), 401);

        $this->logout->execute(
            new UserId($actor->userId()),
            new SessionId($sessionId),
            $request->boolean('all', false),
        );

        return $this->responses->loggedOut();
    }
}
