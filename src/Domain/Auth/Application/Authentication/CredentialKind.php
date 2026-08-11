<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Authentication;

enum CredentialKind: string
{
    case Session = 'session';
    case OAuthAccessToken = 'oauth_access_token';
}
