<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases;

use Illuminate\Support\Facades\Event;
use Polymorph\Platform\Domain\Auth\Application\DTO\CreatedPersonalAccessTokenResult;
use Polymorph\Platform\Domain\Auth\Application\DTO\CreatePersonalAccessTokenCommand;
use Polymorph\Platform\Domain\Auth\Application\Policies\TokenManagementPolicy;
use Polymorph\Platform\Domain\Auth\Events\PersonalAccessTokenCreated;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\PersonalAccessTokenService;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\SharedKernel\Identity\AuthenticatedCredential;

final class AdminCreatePersonalAccessToken
{
    public function __construct(
        private readonly PersonalAccessTokenService $tokens,
        private readonly TokenManagementPolicy $tokenManagementPolicy,
    ) {}

    public function execute(
        CreatePersonalAccessTokenCommand $command,
        User $targetUser,
        ?AuthenticatedCredential $actorCredential,
    ): CreatedPersonalAccessTokenResult {
        $this->tokenManagementPolicy->assertCanManageTokens($actorCredential);

        if (! (bool) config('pat.creation_enabled', true)) {
            throw new \RuntimeException('Personal access token creation is disabled.');
        }

        $created = $this->tokens->create(
            userId: $targetUser->userId(),
            name: $command->name,
            createdByUserId: $command->createdByUserId,
            expiresAt: $this->tokens->resolveExpiresAt($command->ttl),
        );

        Event::dispatch(new PersonalAccessTokenCreated(
            tokenId: (int) $created['token']->id,
            userId: $targetUser->userId(),
            createdByUserId: $command->createdByUserId,
        ));

        return new CreatedPersonalAccessTokenResult($created['token'], $created['plaintext']);
    }
}
