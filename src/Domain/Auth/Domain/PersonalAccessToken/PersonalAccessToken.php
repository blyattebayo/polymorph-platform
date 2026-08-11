<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken;

use DateTimeImmutable;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;

final class PersonalAccessToken
{
    private const MAX_DISPLAY_HINT_LENGTH = 64;

    private readonly string $displayHint;

    private function __construct(
        private readonly PersonalAccessTokenId $id,
        private readonly UserId $userId,
        private readonly PersonalAccessTokenName $name,
        private readonly PersonalAccessTokenDigest $digest,
        string $displayHint,
        private readonly PersonalAccessTokenScopes $scopes,
        private readonly DateTimeImmutable $issuedAt,
        private readonly DateTimeImmutable $expiresAt,
        private ?PersonalAccessTokenRevocation $revocation,
    ) {
        $normalizedHint = trim($displayHint);
        if ($normalizedHint === '' || strlen($normalizedHint) > self::MAX_DISPLAY_HINT_LENGTH) {
            throw new PersonalAccessTokenInvariantViolation(
                PersonalAccessTokenInvariant::InvalidDisplayHint,
                'Personal access token display hint must contain between 1 and 64 bytes.',
            );
        }

        if ($expiresAt <= $issuedAt) {
            throw new PersonalAccessTokenInvariantViolation(
                PersonalAccessTokenInvariant::InvalidExpiration,
                'Personal access token expiry must be after issue time.',
            );
        }

        if ($revocation !== null && $revocation->at < $issuedAt) {
            throw new PersonalAccessTokenInvariantViolation(
                PersonalAccessTokenInvariant::InvalidRevocation,
                'Personal access token cannot be revoked before issue time.',
            );
        }

        $this->displayHint = $normalizedHint;
    }

    public static function issue(
        PersonalAccessTokenId $id,
        UserId $userId,
        PersonalAccessTokenName $name,
        PersonalAccessTokenDigest $digest,
        string $displayHint,
        PersonalAccessTokenScopes $scopes,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
    ): self {
        return new self(
            $id,
            $userId,
            $name,
            $digest,
            $displayHint,
            $scopes,
            $issuedAt,
            $expiresAt,
            null,
        );
    }

    public static function reconstitute(
        PersonalAccessTokenId $id,
        UserId $userId,
        PersonalAccessTokenName $name,
        PersonalAccessTokenDigest $digest,
        string $displayHint,
        PersonalAccessTokenScopes $scopes,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
        ?PersonalAccessTokenRevocation $revocation,
    ): self {
        return new self(
            $id,
            $userId,
            $name,
            $digest,
            $displayHint,
            $scopes,
            $issuedAt,
            $expiresAt,
            $revocation,
        );
    }

    public function id(): PersonalAccessTokenId
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function name(): PersonalAccessTokenName
    {
        return $this->name;
    }

    public function digest(): PersonalAccessTokenDigest
    {
        return $this->digest;
    }

    public function displayHint(): string
    {
        return $this->displayHint;
    }

    public function scopes(): PersonalAccessTokenScopes
    {
        return $this->scopes;
    }

    public function issuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function revocation(): ?PersonalAccessTokenRevocation
    {
        return $this->revocation;
    }

    public function belongsTo(UserId $userId): bool
    {
        return $this->userId->equals($userId);
    }

    public function statusAt(DateTimeImmutable $now): PersonalAccessTokenStatus
    {
        return PersonalAccessTokenStatus::at(
            $this->issuedAt,
            $this->expiresAt,
            $this->revocation?->at,
            $now,
        );
    }

    public function revoke(
        UserId $revokedByUserId,
        PersonalAccessTokenRevocationReason $reason,
        DateTimeImmutable $now,
    ): bool {
        if ($this->revocation !== null) {
            return false;
        }

        if ($now < $this->issuedAt) {
            throw new PersonalAccessTokenInvariantViolation(
                PersonalAccessTokenInvariant::InvalidRevocation,
                'Personal access token cannot be revoked before issue time.',
            );
        }

        $this->revocation = new PersonalAccessTokenRevocation($now, $revokedByUserId, $reason);

        return true;
    }
}
