<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\ValueObjects;

enum CredentialKind: string
{
    case JwtSession = 'jwt';
    case PersonalAccessToken = 'pat';
}
