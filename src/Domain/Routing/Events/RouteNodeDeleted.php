<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Events;

/**
 * Доменное событие: узел маршрута удалён вместе с поддеревом.
 */
final class RouteNodeDeleted
{
    public function __construct(
        public readonly int $routeNodeId,
        public readonly int $deletedCount,
    ) {
    }
}
