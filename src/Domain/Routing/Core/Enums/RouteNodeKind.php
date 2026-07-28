<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Core\Enums;

/**
 * Enum для типов узлов маршрутов (RouteNode).
 *
 * Определяет два типа узлов:
 * - GROUP: группа маршрутов (для организации иерархии, применения middleware, prefix и т.д.)
 * - ROUTE: конкретный маршрут (HTTP endpoint)
 */
enum RouteNodeKind: string
{
    /**
     * Группа маршрутов.
     *
     * Используется для организации иерархии маршрутов, применения общих настроек
     * (prefix, domain, namespace, middleware) к дочерним узлам.
     */
    case GROUP = 'group';

    /**
     * Конкретный маршрут.
     *
     * Представляет HTTP endpoint с определённым URI, методами и действием.
     */
    case ROUTE = 'route';
}
