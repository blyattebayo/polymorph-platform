<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Identity;

final readonly class AuthenticatedCredential
{
    private function __construct(
        public UserIdentity $actor,
        public ?string $sessionId,
    ) {}

    public static function session(UserIdentity $user, string $sessionId): self
    {
        return new self($user, $sessionId);
    }

    public static function oauthAccessToken(UserIdentity $user): self
    {
        return new self($user, null);
    }
}
