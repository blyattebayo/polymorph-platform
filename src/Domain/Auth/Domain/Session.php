<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Domain;

use DateTimeImmutable;
use Polymorph\Platform\Domain\Auth\Domain\Exceptions\AuthInvariantViolation;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\ClientMetadata;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\SessionId;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\TokenHash;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;

final readonly class Session
{
    public function __construct(
        private SessionId $id,
        private UserId $userId,
        private TokenHash $credentialHash,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $expiresAt,
        private ClientMetadata $client,
    ) {
        if ($expiresAt <= $createdAt) {
            throw new AuthInvariantViolation('Session expiry must be after creation.');
        }
    }

    public function id(): SessionId
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function credentialHash(): TokenHash
    {
        return $this->credentialHash;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function client(): ClientMetadata
    {
        return $this->client;
    }
}
