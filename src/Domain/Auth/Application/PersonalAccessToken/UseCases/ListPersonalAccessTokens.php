<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\UseCases;

use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\Data\PersonalAccessTokenView;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenAuthorizer;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenReadModel;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageResult;

final readonly class ListPersonalAccessTokens
{
    public function __construct(
        private PersonalAccessTokenReadModel $tokens,
        private PersonalAccessTokenAuthorizer $authorizer,
    ) {}

    /** @return PageResult<PersonalAccessTokenView> */
    public function execute(PageRequest $page, UserIdentity $actor): PageResult
    {
        $this->authorizer->requireAdministrativeReader($actor);

        return $this->tokens->all($page);
    }
}
