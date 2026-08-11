<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\PersonalAccessToken\Observability;

use LogicException;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenAudit;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenId;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Shared\BestEffortAudit;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

final readonly class LoggedPersonalAccessTokenAudit implements PersonalAccessTokenAudit
{
    public function __construct(
        private BestEffortAudit $audit,
        private AppLogger $logger,
    ) {}

    public function issued(PersonalAccessToken $token): void
    {
        $this->audit->record('pat_issued_audit', fn () => $this->logger->event('auth.personal_access_token.issued', [
            'token_id' => $token->id()->value,
            'user_id' => $token->userId()->value,
            'expires_at' => $token->expiresAt()->format(DATE_ATOM),
        ]), ['token_id' => $token->id()->value]);
    }

    public function revoked(PersonalAccessToken $token): void
    {
        $revocation = $token->revocation()
            ?? throw new LogicException('A PAT revocation audit record requires a revoked aggregate.');

        $this->audit->record('pat_revoked_audit', fn () => $this->logger->event('auth.personal_access_token.revoked', [
            'token_id' => $token->id()->value,
            'user_id' => $token->userId()->value,
            'revoked_by_user_id' => $revocation->byUserId->value,
            'reason' => $revocation->reason->value,
            'revoked_at' => $revocation->at->format(DATE_ATOM),
        ]), ['token_id' => $token->id()->value]);
    }

    public function authenticationDenied(string $reason, ?PersonalAccessTokenId $tokenId = null): void
    {
        $context = ['reason' => $reason];
        if ($tokenId !== null) {
            $context['token_id'] = $tokenId->value;
        }

        $this->audit->record(
            'pat_authentication_denied_audit',
            fn () => $this->logger->event('auth.personal_access_token.denied', $context),
            $tokenId === null ? [] : ['token_id' => $tokenId->value],
        );
    }
}
