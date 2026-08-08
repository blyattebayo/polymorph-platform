<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Identity;

use Illuminate\Http\Request;

/**
 * Разбор учётных данных входящего запроса. Контракт объявлен в SharedKernel, а
 * реализован в домене Auth: так {@see AuthenticationContext} остаётся нижним
 * слоем и не зависит вверх на Auth\Infrastructure.
 *
 * Реализация не бросает 401 сама — неудача возвращается как null (причина
 * кладётся в атрибуты запроса для сборки тела ошибки выше по цепочке).
 */
interface RequestCredentialResolver
{
    public function authenticate(Request $request): ?AuthenticatedCredential;
}
