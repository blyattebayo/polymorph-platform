<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken;

use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenId;

interface PersonalAccessTokenAudit
{
    public function issued(PersonalAccessToken $token): void;

    public function revoked(PersonalAccessToken $token): void;

    public function authenticationDenied(string $reason, ?PersonalAccessTokenId $tokenId = null): void;
}
