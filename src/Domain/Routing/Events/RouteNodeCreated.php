<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Events;

use Polymorph\Platform\Domain\Routing\Core\Models\RouteNode;

/**
 * Доменное событие: создан узел маршрута.
 */
final class RouteNodeCreated
{
    public function __construct(
        public readonly RouteNode $node,
    ) {
    }
}
