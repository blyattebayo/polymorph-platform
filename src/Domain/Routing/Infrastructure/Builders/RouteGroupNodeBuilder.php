<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Builders;

use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;

/**
 * Билдер для создания узлов типа GROUP.
 *
 * Отвечает за создание и настройку групп маршрутов:
 * - Установка полей группы (prefix, domain, namespace, middleware, where)
 * - Рекурсивная обработка дочерних узлов
 *
 * @package Polymorph\Platform\Domain\Routing\Builders
 */
class RouteGroupNodeBuilder implements RouteNodeBuilderInterface
{

    /**
     * Построить RouteNodeDefinition типа GROUP из массива конфигурации.
     *
     * @param array<string, mixed> $data Данные конфигурации
     * @param list<RouteNodeDefinition> $children Дочерние узлы группы
     * @return RouteNodeDefinition Созданный RouteNodeDefinition
     */
    public function build(array $data, array $children): ?RouteNodeDefinition
    {
        return new RouteNodeDefinition(
            kind: RouteNodeKind::GROUP,
            owner: is_string($data['owner'] ?? null) ? $data['owner'] : null,
            sortOrder: (int) ($data['sort_order'] ?? 0),
            enabled: (bool) ($data['enabled'] ?? true),
            name: is_string($data['name'] ?? null) ? $data['name'] : null,
            domain: is_string($data['domain'] ?? null) ? $data['domain'] : null,
            prefix: is_string($data['prefix'] ?? null) ? $data['prefix'] : null,
            namespace: is_string($data['namespace'] ?? null) ? $data['namespace'] : null,
            uri: null,
            methods: [],
            actionType: null,
            actionMeta: [],
            middleware: is_array($data['middleware'] ?? null) ? array_values($data['middleware']) : [],
            where: is_array($data['where'] ?? null) ? $data['where'] : [],
            defaults: [],
            children: $children,
        );
    }
}
