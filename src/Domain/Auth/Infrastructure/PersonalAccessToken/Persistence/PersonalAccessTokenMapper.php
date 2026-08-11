<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\PersonalAccessToken\Persistence;

use DateTimeInterface;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenDigest;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenId;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenInvariant;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenInvariantViolation;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenName;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenRevocation;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenRevocationReason;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenScopes;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;

final class PersonalAccessTokenMapper
{
    public function toDomain(PersonalAccessTokenRecord $record): PersonalAccessToken
    {
        return PersonalAccessToken::reconstitute(
            id: new PersonalAccessTokenId((string) $record->id),
            userId: new UserId((int) $record->user_id),
            name: new PersonalAccessTokenName((string) $record->name),
            digest: new PersonalAccessTokenDigest((string) $record->secret_digest),
            displayHint: (string) $record->display_hint,
            scopes: PersonalAccessTokenScopes::fromArray($record->scopes),
            issuedAt: $record->issued_at->toDateTimeImmutable(),
            expiresAt: $record->expires_at->toDateTimeImmutable(),
            revocation: $this->revocation($record),
        );
    }

    /** @return array<string, mixed> */
    public function toPersistence(PersonalAccessToken $token): array
    {
        return [
            'id' => $token->id()->value,
            'user_id' => $token->userId()->value,
            'name' => $token->name()->value,
            'secret_digest' => $token->digest()->value,
            'display_hint' => $token->displayHint(),
            'scopes' => $token->scopes()->toArray(),
            'issued_at' => $this->timestamp($token->issuedAt()),
            'expires_at' => $this->timestamp($token->expiresAt()),
            ...$this->revocationToPersistence($token->revocation()),
        ];
    }

    /** @return array{revoked_at: mixed, revoked_by_user_id: int|null, revocation_reason: string|null} */
    public function revocationToPersistence(?PersonalAccessTokenRevocation $revocation): array
    {
        return [
            'revoked_at' => $revocation === null ? null : $this->timestamp($revocation->at),
            'revoked_by_user_id' => $revocation?->byUserId->value,
            'revocation_reason' => $revocation?->reason->value,
        ];
    }

    private function revocation(PersonalAccessTokenRecord $record): ?PersonalAccessTokenRevocation
    {
        $values = [$record->revoked_at, $record->revoked_by_user_id, $record->revocation_reason];
        $present = count(array_filter($values, static fn (mixed $value): bool => $value !== null));

        if ($present === 0) {
            return null;
        }

        if ($present !== count($values)) {
            throw new PersonalAccessTokenInvariantViolation(
                PersonalAccessTokenInvariant::InvalidRevocation,
                'Persisted personal access token revocation is incomplete.',
            );
        }

        $reason = PersonalAccessTokenRevocationReason::tryFrom((string) $record->revocation_reason);
        if ($reason === null) {
            throw new PersonalAccessTokenInvariantViolation(
                PersonalAccessTokenInvariant::InvalidRevocation,
                'Persisted personal access token revocation reason is unknown.',
            );
        }

        return new PersonalAccessTokenRevocation(
            $record->revoked_at->toDateTimeImmutable(),
            new UserId((int) $record->revoked_by_user_id),
            $reason,
        );
    }

    private function timestamp(DateTimeInterface $value): string
    {
        return $value->format('Y-m-d H:i:s.uP');
    }
}
