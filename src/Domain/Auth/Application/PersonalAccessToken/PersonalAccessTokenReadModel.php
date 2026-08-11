<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken;

use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\Data\PersonalAccessTokenView;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageResult;

interface PersonalAccessTokenReadModel
{
    /** @return list<PersonalAccessTokenView> */
    public function forUser(UserId $userId): array;

    /** @return PageResult<PersonalAccessTokenView> */
    public function all(PageRequest $page): PageResult;
}
