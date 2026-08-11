<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Auth\Application\UseCases\Session\Login;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\ClientMetadata;
use Polymorph\Platform\Domain\Auth\Http\Requests\LoginRequest;
use Polymorph\Platform\Domain\Auth\Http\Support\AuthHttpResponder;

final readonly class LoginController
{
    public function __construct(
        private Login $login,
        private AuthHttpResponder $responses,
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $result = $this->login->execute(
            (string) $request->input('email'),
            (string) $request->input('password'),
            new ClientMetadata($request->ip(), $request->userAgent()),
        );

        return $this->responses->authenticated($result);
    }
}
