<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\UseCases;

use Polymorph\Platform\Domain\Auth\Application\Contracts\Clock;
use Polymorph\Platform\Domain\Auth\Application\Contracts\UserGateway;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\Data\AuthenticatedPersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenAudit;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenRepository;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenScopeCatalog;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenSecretCodec;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenStatus;
use SensitiveParameter;

final readonly class AuthenticatePersonalAccessToken
{
    public function __construct(
        private PersonalAccessTokenRepository $repository,
        private PersonalAccessTokenSecretCodec $secrets,
        private PersonalAccessTokenScopeCatalog $scopeCatalog,
        private UserGateway $users,
        private Clock $clock,
        private PersonalAccessTokenAudit $audit,
    ) {}

    public function execute(#[SensitiveParameter] string $plaintext): ?AuthenticatedPersonalAccessToken
    {
        if (! $this->secrets->supports($plaintext)) {
            $this->audit->authenticationDenied('unsupported_format');

            return null;
        }

        $token = $this->repository->findByDigest($this->secrets->digest($plaintext));
        $now = $this->clock->now();

        if ($token === null) {
            $this->audit->authenticationDenied('not_found');

            return null;
        }

        $status = $token->statusAt($now);
        if ($status !== PersonalAccessTokenStatus::Active) {
            $this->audit->authenticationDenied($status->value, $token->id());

            return null;
        }

        $user = $this->users->findById($token->userId());
        if ($user === null || ! $user->identity->isActiveAccount()) {
            $this->audit->authenticationDenied('inactive_user', $token->id());

            return null;
        }

        if ($this->scopeCatalog->unknownScopes($token->scopes()) !== []) {
            $this->audit->authenticationDenied('unknown_scopes', $token->id());

            return null;
        }

        $result = new AuthenticatedPersonalAccessToken(
            actor: $user->identity,
            scopes: $token->scopes()->toCredentialScopes(),
        );

        $this->repository->recordSuccessfulUse($token->id(), $now);

        return $result;
    }
}
