<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Registrars;

use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;

/**
 * Интерфейс для регистраторов узлов маршрутов.
 *
 * Определяет контракт для регистрации узла в Laravel Router.
 * Каждый регистратор отвечает за регистрацию узлов определённого типа (GROUP или ROUTE).
 *
 * @package Polymorph\Platform\Domain\Routing\Registrars
 */
interface RouteNodeRegistrarInterface
{
    /**
     * Зарегистрировать узел маршрута.
     *
     * Регистрирует узел в Laravel Router в соответствии с его типом и настройками.
     * Выполняет валидацию, построение атрибутов и регистрацию маршрута/группы.
     *
     * @param RouteNodeDefinition $node Узел для регистрации
     * @return void
     */
    public function register(RouteNodeDefinition $node): void;
}
