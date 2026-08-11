<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\Data;

use DateTimeImmutable;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenStatus;

final readonly class PersonalAccessTokenView
{
    /**
     * @param  non-empty-list<array{resource: string, action: string}>  $scopes
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $displayHint,
        public array $scopes,
        public PersonalAccessTokenStatus $status,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $lastUsedAt,
        public ?PersonalAccessTokenOwnerView $owner = null,
    ) {}

    public static function fromAggregate(PersonalAccessToken $token, DateTimeImmutable $now): self
    {
        return new self(
            id: $token->id()->value,
            name: $token->name()->value,
            displayHint: $token->displayHint(),
            scopes: $token->scopes()->toArray(),
            status: $token->statusAt($now),
            issuedAt: $token->issuedAt(),
            expiresAt: $token->expiresAt(),
            lastUsedAt: null,
        );
    }
}
