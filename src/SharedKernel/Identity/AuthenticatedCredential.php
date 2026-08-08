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
        /** Когда истекает сам credential, unix-время. */
        public ?int $expiresAt = null,
        /** Потолок жизни семьи сессий, unix-время; null — не задан. */
        public ?int $absoluteExpiresAt = null,
    ) {}

    /**
     * Сроки принимаются здесь, а не вычитываются повторно теми, кому они нужны:
     * тот, кто credential выдал, эти значения уже проверил. Из-за их отсутствия
     * продление куки перепроверяло тот же токен вторым HMAC на каждом запросе.
     */
    public static function session(
        User $user,
        ?int $sessionId = null,
        ?int $expiresAt = null,
        ?int $absoluteExpiresAt = null,
    ): self {
        return new self($user, CredentialKind::Session, $sessionId, $expiresAt, $absoluteExpiresAt);
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
}
