<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Polymorph\Platform\Domain\Auth\Application\DTO\LoginSessionCommand;
use Polymorph\Platform\Domain\Auth\Application\DTO\LoginSessionResult;
use Polymorph\Platform\Domain\Auth\Application\Exceptions\AuthSessionUnauthorizedException;
use Polymorph\Platform\Domain\Auth\Application\Support\UserCapabilitiesPresenter;
use Polymorph\Platform\Domain\Auth\Core\Contracts\RefreshSessionRepository;
use Polymorph\Platform\Domain\Auth\Events\UserLoggedIn;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\JwtService;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Domain\Users\Queries\FindUserByEmailQuery;

final class LoginSession
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly RefreshSessionRepository $sessions,
        private readonly FindUserByEmailQuery $findUserByEmail,
        private readonly UserCapabilitiesPresenter $capabilities,
    ) {}

    public function execute(LoginSessionCommand $command): LoginSessionResult
    {
        $user = $this->findUserByEmail->execute($command->email);

        if (! $user instanceof User || ! Hash::check($command->password, (string) $user->password)) {
            throw new AuthSessionUnauthorizedException('Invalid credentials.');
        }

        if (! $user->isActiveAccount()) {
            $this->sessions->revokeAllForUser((int) $user->id);
            throw new AuthSessionUnauthorizedException('Account is not active.');
        }

        $refreshSession = $this->sessions->createForUser((int) $user->id, $command->ip, $command->userAgent);
        $accessToken = $this->jwt->issueAccessToken((int) $user->id, [
            'sid' => $refreshSession->sessionId,
            // Абсолютный потолок скользящей сессии: дальше него access-токен
            // не продлевается (см. RenewAccessTokenCookie).
            'aex' => $refreshSession->absoluteExpiresAt,
        ]);

        Event::dispatch(new UserLoggedIn(
            user: $user,
            ip: $command->ip,
            userAgent: $command->userAgent,
        ));

        return new LoginSessionResult(
            user: $user,
            accessToken: $accessToken,
            refreshToken: $refreshSession->refreshToken,
            capabilities: $this->capabilities->for($user),
        );
    }
}
