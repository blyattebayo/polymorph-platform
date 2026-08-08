<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Identity;

use Polymorph\Platform\Domain\Users\Core\Models\User;

/**
 * Кто аутентифицирован в запросе и чем это доказано.
 *
 * Единственный носитель личности запроса: пользователь берётся отсюда, вид
 * credential — отсюда же. Раньше то же знание жило в четырёх местах (два поля
 * гарда и четыре атрибута запроса, два из которых никто не читал) и в двух
 * местах синтезировалось независимо.
 *
 * Конструктор закрыт: у каждого вида свой набор осмысленных полей, и именованные
 * фабрики не дают собрать невозможную комбинацию (PAT с sessionId и т.п.).
 */
final readonly class AuthenticatedCredential
{
    private function __construct(
        public User $user,
        public CredentialKind $kind,
        /** Id строки auth_sessions — только у интерактивной сессии. */
        public ?int $sessionId = null,
    ) {}

    public static function session(User $user, ?int $sessionId = null): self
    {
        return new self($user, CredentialKind::Session, $sessionId);
    }

    public static function personalAccessToken(User $user): self
    {
        return new self($user, CredentialKind::PersonalAccessToken);
    }

    /**
     * Актор выставлен кодом (`Auth::setUser()`), а не разобран из запроса.
     */
    public static function assumed(User $user): self
    {
        return new self($user, CredentialKind::Assumed);
    }

    /**
     * Интерактивная сессия — единственный вид, которому разрешено управлять
     * сессиями и персональными токенами. Предикат один на всю систему: и
     * middleware `session.credential`, и `TokenManagementPolicy` спрашивают
     * его, поэтому разъехаться им негде.
     */
    public function isSession(): bool
    {
        return $this->kind === CredentialKind::Session;
    }

    public function isPersonalAccessToken(): bool
    {
        return $this->kind === CredentialKind::PersonalAccessToken;
    }

    public function isAssumed(): bool
    {
        return $this->kind === CredentialKind::Assumed;
    }
}
