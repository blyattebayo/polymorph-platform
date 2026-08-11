<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\UseCases;

use DateTimeImmutable;
use Polymorph\Platform\Domain\Auth\Application\Contracts\Clock;
use Polymorph\Platform\Domain\Auth\Application\Contracts\IdGenerator;
use Polymorph\Platform\Domain\Auth\Application\Contracts\TransactionManager;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\Data\IssuedPersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\Data\PersonalAccessTokenView;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\InvalidPersonalAccessTokenDefinition;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenAudit;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenAuthorizer;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenRepository;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenScopeCatalog;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenSecretCodec;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenInvariantViolation;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenName;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenScopes;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

final readonly class IssuePersonalAccessToken
{
    private const MAX_LIFETIME_DAYS = 365;

    public function __construct(
        private PersonalAccessTokenRepository $repository,
        private PersonalAccessTokenSecretCodec $secrets,
        private PersonalAccessTokenAuthorizer $authorizer,
        private PersonalAccessTokenScopeCatalog $scopeCatalog,
        private IdGenerator $ids,
        private Clock $clock,
        private TransactionManager $transactions,
        private PersonalAccessTokenAudit $audit,
    ) {}

    public function execute(
        string $name,
        DateTimeImmutable $expiresAt,
        array $scopes,
        UserIdentity $actor,
    ): IssuedPersonalAccessToken {
        $actorId = $this->authorizer->requireSelfServiceActor($actor);

        $issuedAt = $this->clock->now();

        if ($expiresAt > $issuedAt->modify('+'.self::MAX_LIFETIME_DAYS.' days')) {
            throw InvalidPersonalAccessTokenDefinition::expirationExceedsMaximum();
        }

        try {
            $tokenName = new PersonalAccessTokenName($name);
            $tokenScopes = PersonalAccessTokenScopes::fromArray($scopes);
        } catch (PersonalAccessTokenInvariantViolation $violation) {
            throw InvalidPersonalAccessTokenDefinition::fromInvariant($violation);
        }

        $unknownScopes = $this->scopeCatalog->unknownScopes($tokenScopes);
        if ($unknownScopes !== []) {
            throw InvalidPersonalAccessTokenDefinition::unknownScopes($unknownScopes);
        }

        $secret = $this->secrets->generate();

        try {
            $token = PersonalAccessToken::issue(
                id: $this->ids->personalAccessTokenId(),
                userId: $actorId,
                name: $tokenName,
                digest: $secret->digest,
                displayHint: $secret->displayHint,
                scopes: $tokenScopes,
                issuedAt: $issuedAt,
                expiresAt: $expiresAt,
            );
        } catch (PersonalAccessTokenInvariantViolation $violation) {
            throw InvalidPersonalAccessTokenDefinition::fromInvariant($violation);
        }

        $this->transactions->run(fn () => $this->repository->add($token));
        $this->audit->issued($token);

        return new IssuedPersonalAccessToken(
            PersonalAccessTokenView::fromAggregate($token, $issuedAt),
            $secret,
        );
    }
}
