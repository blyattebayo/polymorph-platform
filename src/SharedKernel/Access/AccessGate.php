<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Access;

use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

/**
 * Единственный порт вопроса «можно ли актору Х делать Y с ресурсом Z».
 *
 * До него каждый потребитель инжектил тройку PolicyRuntime +
 * AccessSubjectProvider + CurrentActorResolver и склеивал решение руками —
 * механика «субъекты(актор) × скомпилированные политики» была размазана по
 * семи местам (middleware, контроллеры, полевые сервисы, SDK-мост). Теперь
 * она живёт в одной реализации, а домены знают только свой словарь ресурсов.
 *
 * Fail-closed: отсутствие актора — всегда deny. «Системные» пути, которым
 * доступ не нужен, просто не спрашивают гейт.
 */
interface AccessGate
{
    public function allows(?UserIdentity $actor, ResourceRef $resource, string $action): bool;

    /** Для текущего аутентифицированного актора запроса (нет актора — false). */
    public function currentActorAllows(ResourceRef $resource, string $action): bool;

    /**
     * Батч одним SQL-запросом — для массовых проверок (видимость полей, каталог).
     *
     * @param  list<AccessCheck>  $checks
     * @return list<bool> в порядке $checks
     */
    public function allowsEach(?UserIdentity $actor, array $checks): array;
}
