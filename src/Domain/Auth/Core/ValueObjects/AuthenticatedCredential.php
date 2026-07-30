<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\ValueObjects;

use Polymorph\Platform\Domain\Auth\Core\Models\PersonalAccessToken;
use Polymorph\Platform\Domain\Users\Core\Models\User;

final readonly class AuthenticatedCredential
{
    public const REQUEST_ATTRIBUTE = self::class;

    public function __construct(
        public User $user,
        public CredentialKind $kind,
        public ?PersonalAccessToken $personalAccessToken = null,
        public ?int $sessionId = null,
    ) {}

    /**
     * «Реального credential нет, но пользователь аутентифицирован — считаем
     * интерактивной сессией». Единственная точка этого допущения: раньше оно
     * синтезировалось независимо в ApiGuard::syncUserAttributes и
     * AuthenticatedCredentialResolver и однажды разъехалось бы.
     * Штатные пути (RequestCredentialAuthenticator) сюда не ходят —
     * это фолбэк для setUser()/actingAs.
     */
    public static function assumedSession(User $user): self
    {
        return new self($user, CredentialKind::JwtSession);
    }

    public function isPat(): bool
    {
        return $this->kind === CredentialKind::PersonalAccessToken;
    }

    public function isJwtSession(): bool
    {
        return $this->kind === CredentialKind::JwtSession;
    }

    /**
     * @return non-empty-string
     */
    public function method(): string
    {
        return $this->kind->value;
    }
}
