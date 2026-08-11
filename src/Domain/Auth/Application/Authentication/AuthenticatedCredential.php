<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Authentication;

use InvalidArgumentException;
use Polymorph\Platform\Domain\Users\Core\Models\User;

final readonly class AuthenticatedCredential
{
    private function __construct(
        public User $user,
        public CredentialKind $kind,
        private ?string $sessionId,
    ) {}

    public static function session(User $user, string $sessionId): self
    {
        if ($sessionId === '') {
            throw new InvalidArgumentException('Session credential requires a session id.');
        }

        return new self($user, CredentialKind::Session, $sessionId);
    }

    public static function oauthAccessToken(User $user): self
    {
        return new self($user, CredentialKind::OAuthAccessToken, null);
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }
}
