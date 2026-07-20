<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Builders;

use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;

/**
 * Билдер для создания узлов типа ROUTE.
 *
 * Отвечает за создание и настройку конкретных маршрутов:
 * - Установка полей маршрута (uri, methods, name, domain, middleware, where, defaults)
 * - Обработка action_type и action_meta
 */
class RouteRouteNodeBuilder implements RouteNodeBuilderInterface
{
    /**
     * Построить RouteNodeDefinition типа ROUTE из массива конфигурации.
     *
     * @param  array<string, mixed>  $data  Данные конфигурации
     * @param  list<RouteNodeDefinition>  $children  Дочерние узлы (игнорируются для ROUTE)
     * @return RouteNodeDefinition Созданный RouteNodeDefinition
     */
    public function build(array $data, array $children): ?RouteNodeDefinition
    {
        return new RouteNodeDefinition(
            kind: RouteNodeKind::ROUTE,
            owner: is_string($data['owner'] ?? null) ? $data['owner'] : null,
            sortOrder: (int) ($data['sort_order'] ?? 0),
            enabled: (bool) ($data['enabled'] ?? true),
            name: is_string($data['name'] ?? null) ? $data['name'] : null,
            domain: is_string($data['domain'] ?? null) ? $data['domain'] : null,
            prefix: null,
            namespace: null,
            uri: is_string($data['uri'] ?? null) ? $data['uri'] : null,
            methods: is_array($data['methods'] ?? null) ? array_values($data['methods']) : [],
            actionType: isset($data['action_type']) ? RouteNodeActionType::from((string) $data['action_type']) : null,
            actionMeta: is_array($data['action_meta'] ?? null) ? $data['action_meta'] : [],
            middleware: is_array($data['middleware'] ?? null) ? array_values($data['middleware']) : [],
            where: is_array($data['where'] ?? null) ? $data['where'] : [],
            defaults: is_array($data['defaults'] ?? null) ? $data['defaults'] : [],
            children: [],
        );
    }
}
