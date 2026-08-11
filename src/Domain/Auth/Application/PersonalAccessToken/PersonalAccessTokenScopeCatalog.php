<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken;

use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenScopes;

interface PersonalAccessTokenScopeCatalog
{
    /** @return list<array{resource: string, action: string}> */
    public function unknownScopes(PersonalAccessTokenScopes $scopes): array;
}
