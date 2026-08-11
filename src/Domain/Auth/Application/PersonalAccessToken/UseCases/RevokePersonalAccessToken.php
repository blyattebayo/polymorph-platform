<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\UseCases;

use Polymorph\Platform\Domain\Auth\Application\Contracts\Clock;
use Polymorph\Platform\Domain\Auth\Application\Contracts\TransactionManager;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenAudit;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenAuthorizer;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenNotFound;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenRepository;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenId;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenRevocationReason;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

final readonly class RevokePersonalAccessToken
{
    public function __construct(
        private PersonalAccessTokenRepository $repository,
        private PersonalAccessTokenAuthorizer $authorizer,
        private Clock $clock,
        private TransactionManager $transactions,
        private PersonalAccessTokenAudit $audit,
    ) {}

    public function revokeOwn(PersonalAccessTokenId $tokenId, UserIdentity $actor): bool
    {
        return $this->revoke(
            $tokenId,
            $this->authorizer->requireSelfServiceActor($actor),
            PersonalAccessTokenRevocationReason::UserRequested,
        );
    }

    public function revokeAsAdministrator(
        PersonalAccessTokenId $tokenId,
        UserIdentity $actor,
    ): bool {
        return $this->revoke(
            $tokenId,
            $this->authorizer->requireAdministrativeManager($actor),
            PersonalAccessTokenRevocationReason::Administrator,
        );
    }

    private function revoke(
        PersonalAccessTokenId $tokenId,
        UserId $actorId,
        PersonalAccessTokenRevocationReason $reason,
    ): bool {
        $revoked = $this->transactions->run(function () use ($tokenId, $actorId, $reason): ?PersonalAccessToken {
            $token = $this->repository->findByIdForUpdate($tokenId);

            if ($token === null
                || ($reason === PersonalAccessTokenRevocationReason::UserRequested && ! $token->belongsTo($actorId))) {
                throw PersonalAccessTokenNotFound::token();
            }

            $changed = $token->revoke(
                $actorId,
                $reason,
                $this->clock->now(),
            );

            if ($changed) {
                $this->repository->save($token);
            }

            return $changed ? $token : null;
        });

        if ($revoked instanceof PersonalAccessToken) {
            $this->audit->revoked($revoked);

            return true;
        }

        return false;
    }
}
