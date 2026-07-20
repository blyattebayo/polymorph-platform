<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Listeners;

use Polymorph\Platform\Domain\Routing\Events\RouteNodeCreated;
use Polymorph\Platform\Domain\Routing\Events\RouteNodeDeleted;
use Polymorph\Platform\Domain\Routing\Events\RouteNodeUpdated;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Структурированное логирование жизненного цикла узлов маршрутов.
 *
 * Раньше RouteNodeService логировал инлайн строкой `logger->event('routing.node.*')`
 * без диспатча события. Теперь сервис эмитит настоящие доменные события, а
 * логирование — отдельный листенер (симметрично LogUserLifecycleEvent).
 */
final class LogRouteNodeLifecycle
{
    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    public function handleCreated(RouteNodeCreated $event): void
    {
        $this->logger->event('routing.node.created', [
            'id' => $event->node->id,
            'owner' => $event->node->owner,
        ]);
    }

    public function handleUpdated(RouteNodeUpdated $event): void
    {
        $this->logger->event('routing.node.updated', ['id' => $event->node->id]);
    }

    public function handleDeleted(RouteNodeDeleted $event): void
    {
        $this->logger->event('routing.node.deleted_with_children', [
            'route_node_id' => $event->routeNodeId,
            'deleted_count' => $event->deletedCount,
        ]);
    }
}
