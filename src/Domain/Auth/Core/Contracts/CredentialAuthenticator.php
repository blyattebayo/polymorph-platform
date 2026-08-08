<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\Contracts;

use Polymorph\Platform\Domain\Auth\Core\ValueObjects\PresentedToken;
use Polymorph\Platform\SharedKernel\Identity\AuthenticatedCredential;

/**
 * Один способ аутентификации по предъявленному токену.
 *
 * Способ сам объявляет, свой ли это токен (`supports`), и сам его проверяет
 * (`attempt`). Диспетчер — CredentialAuthenticatorRegistry — ни о JWT, ни о PAT
 * не знает: он идёт по реестру и берёт первый подходящий.
 *
 * Раньше выбор был захардкожен в if: «похоже на PAT — PAT, иначе JWT». JWT был
 * не способом, а веткой else, и третий способ было некуда добавить, не правив
 * этот if.
 */
interface CredentialAuthenticator
{
    /** Тег контейнера: реализации собираются в реестр по нему. */
    public const TAG = 'auth.credential_authenticators';

    /**
     * Дешёвая проверка формы — без обращений к БД и криптографии.
     * Реализации обязаны быть взаимоисключающими: побеждает первая в реестре.
     */
    public function supports(PresentedToken $token): bool;

    /**
     * Полная проверка. Вызывается только после успешного supports().
     * Отказ (просрочен, отозван, подпись не сходится) — это null, не исключение.
     */
    public function attempt(PresentedToken $token): ?AuthenticatedCredential;
}
