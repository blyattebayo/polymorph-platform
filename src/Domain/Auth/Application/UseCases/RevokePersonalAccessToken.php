<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases;

use Illuminate\Support\Facades\Event;
use Polymorph\Platform\Domain\Auth\Application\Policies\TokenManagementPolicy;
use Polymorph\Platform\Domain\Auth\Core\Contracts\PersonalAccessTokenRepository;
use Polymorph\Platform\Domain\Auth\Events\PersonalAccessTokenRevoked;
use Polymorph\Platform\SharedKernel\Identity\AuthenticatedCredential;

/**
 * Отзыв персонального токена — один на оба сценария. Были RevokeOwn и
 * AdminRevoke, различавшиеся одной строкой выбора метода репозитория.
 */
final class RevokePersonalAccessToken
{
    public function __construct(
        private readonly PersonalAccessTokenRepository $repository,
        private readonly TokenManagementPolicy $tokenManagementPolicy,
    ) {}

    /**
     * @param  int|null  $restrictToUserId  владелец, которым ограничен отзыв;
     *                                      null — отзыв без ограничения (админский путь,
     *                                      закрытый capability на маршруте)
     */
    public function execute(
        int $tokenId,
        ?int $restrictToUserId,
        ?AuthenticatedCredential $actorCredential,
    ): bool {
        $this->tokenManagementPolicy->assertCanManageTokens($actorCredential);

        $revoked = $restrictToUserId === null
            ? $this->repository->revoke($tokenId)
            : $this->repository->revokeForUser($tokenId, $restrictToUserId);

        if ($revoked) {
            Event::dispatch(new PersonalAccessTokenRevoked($tokenId, $restrictToUserId));
        }

        return $revoked;
    }
}
