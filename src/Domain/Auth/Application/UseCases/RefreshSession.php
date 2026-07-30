<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases;

use Polymorph\Platform\Domain\Auth\Application\DTO\RefreshSessionCommand;
use Polymorph\Platform\Domain\Auth\Application\DTO\RefreshSessionResult;
use Polymorph\Platform\Domain\Auth\Application\Exceptions\AuthSessionUnauthorizedException;
use Polymorph\Platform\Domain\Auth\Core\Contracts\RefreshSessionRepository;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\JwtService;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Domain\Users\Queries\FindUserByIdQuery;

final readonly class RefreshSession
{
    public function __construct(
        private JwtService $jwt,
        private RefreshSessionRepository $sessions,
        private FindUserByIdQuery $findUserByIdQuery,
    ) {}

    public function execute(RefreshSessionCommand $command): RefreshSessionResult
    {
        $rotation = $this->sessions->rotate($command->refreshToken, $command->ip, $command->userAgent);
        $user = $this->findUserByIdQuery->execute($rotation->userId);
        if (! $user instanceof User) {
            throw new AuthSessionUnauthorizedException('Invalid or expired refresh token.');
        }

        if (! $user->isActiveAccount()) {
            $this->sessions->revokeAllForUser((int) $user->id);
            throw new AuthSessionUnauthorizedException('Account is not active.');
        }

        $accessToken = $this->jwt->issueAccessToken($rotation->userId, [
            'sid' => $rotation->sessionId,
            'aex' => $rotation->absoluteExpiresAt,
        ]);

        return new RefreshSessionResult($accessToken, $rotation->refreshToken);
    }
}
