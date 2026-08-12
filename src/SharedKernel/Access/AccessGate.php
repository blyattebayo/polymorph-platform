<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Access;

use Polymorph\Platform\Domain\Users\Core\Models\User;

/**
 * Единственный порт вопроса «можно ли актору Х делать Y с ресурсом Z».
 *
 * Реализация сама получает субъекты актора и читает назначенные политики из
 * канонических таблиц. Домены знают только свой словарь ресурсов.
 *
 * Fail-closed: отсутствие актора — всегда deny. «Системные» пути, которым
 * доступ не нужен, просто не спрашивают гейт.
 */
interface AccessGate
{
    public function allows(?User $user, ResourceRef $resource, string $action): bool;

    /** Для текущего аутентифицированного актора запроса (нет актора — false). */
    public function currentUserAllows(ResourceRef $resource, string $action): bool;

    /**
     * Батч использует один набор правил — для массовых проверок (видимость полей, каталог).
     *
     * @param  list<AccessCheck>  $checks
     * @return list<bool> в порядке $checks
     */
    public function allowsEach(?User $user, array $checks): array;
}
