<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Identity;

use Illuminate\Http\Request;

/**
 * Разбор учётных данных входящего запроса. Контракт объявлен в SharedKernel, а
 * реализован в домене Auth: так {@see AuthenticationContext} остаётся нижним
 * слоем и не зависит вверх на Auth\Infrastructure.
 *
 * Null означает только отсутствие credential. Представленный, но неверный или
 * неоднозначный credential обязан завершиться типизированным отказом.
 */
interface RequestCredentialResolver
{
    public function authenticate(Request $request): ?AuthenticatedCredential;
}
