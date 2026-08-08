<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Identity;

/**
 * Чем доказана личность в текущем запросе.
 *
 * Живёт в SharedKernel, а не в домене Auth: вид credential спрашивают из
 * middleware, политик и адаптеров SDK, а конкретный механизм проверки (JWT,
 * хеш PAT) остаётся деталью Auth и в это перечисление не просачивается —
 * поэтому `Session`, а не `JwtSession`.
 */
enum CredentialKind: string
{
    /** Интерактивный вход: access-токен в куке или Bearer. */
    case Session = 'session';

    /** Долгоживущий машинный токен пользователя. */
    case PersonalAccessToken = 'pat';

    /**
     * Актор задан кодом напрямую (`Auth::setUser()`, `actingAs` в тестах).
     * Транспортного доказательства нет, поэтому привилегий интерактивной
     * сессии этот вид не получает — см. {@see AuthenticatedCredential::isSession()}.
     */
    case Assumed = 'assumed';
}
