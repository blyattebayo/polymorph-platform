<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases;

use Illuminate\Support\Facades\Event;
use Polymorph\Platform\Domain\Auth\Application\Policies\TokenManagementPolicy;
use Polymorph\Platform\Domain\Auth\Core\Contracts\PersonalAccessTokenRepository;
use Polymorph\Platform\Domain\Auth\Events\PersonalAccessTokenRevoked;
use Polymorph\Platform\SharedKernel\Identity\AuthenticatedCredential;

final class AdminRevokePersonalAccessToken
{
    public function __construct(
        private readonly PersonalAccessTokenRepository $repository,
        private readonly TokenManagementPolicy $tokenManagementPolicy,
    ) {}

    public function execute(int $tokenId, ?AuthenticatedCredential $actorCredential): bool
    {
        $this->tokenManagementPolicy->assertCanManageTokens($actorCredential);

        $revoked = $this->repository->revoke($tokenId);

        if ($revoked) {
            Event::dispatch(new PersonalAccessTokenRevoked($tokenId, null));
        }

        return $revoked;
    }
}
