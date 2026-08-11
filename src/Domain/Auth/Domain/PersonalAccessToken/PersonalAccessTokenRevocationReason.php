<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken;

enum PersonalAccessTokenRevocationReason: string
{
    case UserRequested = 'user_requested';
    case Administrator = 'administrator';
}
