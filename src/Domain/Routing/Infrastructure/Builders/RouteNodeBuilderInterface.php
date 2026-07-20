<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Builders;

use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;

/**
 * Интерфейс для билдеров узлов маршрутов.
 *
 * Определяет контракт для создания RouteNode объектов из массива конфигурации.
 * Каждый билдер регистрируется для определённого RouteNodeKind
 * в RouteNodeBuilderFactory и вызывается напрямую без проверки supports().
 */
interface RouteNodeBuilderInterface
{
    /**
     * Построить RouteNodeDefinition из массива конфигурации.
     *
     * Создаёт и настраивает ОДИН узел RouteNodeDefinition.
     *
     * @param  array<string, mixed>  $data  Данные конфигурации (провалидированные)
     * @param  list<RouteNodeDefinition>  $children  Готовые дочерние узлы
     * @return RouteNodeDefinition|null Созданный RouteNodeDefinition
     */
    public function build(array $data, array $children): ?RouteNodeDefinition;
}
