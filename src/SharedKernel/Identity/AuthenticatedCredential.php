<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Identity;

use Polymorph\Platform\SharedKernel\Access\CredentialScopes;

final readonly class AuthenticatedCredential
{
    private function __construct(
        public UserIdentity $actor,
        public CredentialScopes $scopes,
        public ?string $sessionId,
    ) {}

    public static function session(UserIdentity $user, string $sessionId): self
    {
        return new self($user, CredentialScopes::unrestricted(), $sessionId);
    }

    public static function personalAccessToken(UserIdentity $user, CredentialScopes $scopes): self
    {
        return new self($user, $scopes, null);
    }
}
