<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken;

enum PersonalAccessTokenInvariant: string
{
    case InvalidId = 'invalid_pat_id';
    case InvalidName = 'invalid_pat_name';
    case InvalidDigest = 'invalid_pat_digest';
    case InvalidScopes = 'invalid_pat_scopes';
    case InvalidExpiration = 'invalid_pat_expiration';
    case InvalidDisplayHint = 'invalid_pat_display_hint';
    case InvalidRevocation = 'invalid_pat_revocation';
}
