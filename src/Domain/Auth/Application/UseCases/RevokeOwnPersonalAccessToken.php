<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases;

use Illuminate\Support\Facades\Event;
use Polymorph\Platform\Domain\Auth\Application\Policies\TokenManagementPolicy;
use Polymorph\Platform\Domain\Auth\Core\Contracts\PersonalAccessTokenRepository;
use Polymorph\Platform\Domain\Auth\Events\PersonalAccessTokenRevoked;
use Polymorph\Platform\SharedKernel\Identity\AuthenticatedCredential;

final class RevokeOwnPersonalAccessToken
{
    public function __construct(
        private readonly PersonalAccessTokenRepository $repository,
        private readonly TokenManagementPolicy $tokenManagementPolicy,
    ) {}

    public function execute(
        int $tokenId,
        int $userId,
        ?AuthenticatedCredential $actorCredential,
    ): bool {
        $this->tokenManagementPolicy->assertCanManageTokens($actorCredential);

        $revoked = $this->repository->revokeForUser($tokenId, $userId);

        if ($revoked) {
            Event::dispatch(new PersonalAccessTokenRevoked($tokenId, $userId));
        }

        return $revoked;
    }
}
