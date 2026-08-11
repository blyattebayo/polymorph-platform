<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\UseCases;

use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\Data\PersonalAccessTokenView;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenAuthorizer;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenReadModel;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

final readonly class ListOwnPersonalAccessTokens
{
    public function __construct(
        private PersonalAccessTokenReadModel $tokens,
        private PersonalAccessTokenAuthorizer $authorizer,
    ) {}

    /** @return list<PersonalAccessTokenView> */
    public function execute(UserIdentity $actor): array
    {
        return $this->tokens->forUser($this->authorizer->requireSelfServiceActor($actor));
    }
}
