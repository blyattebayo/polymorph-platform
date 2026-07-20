<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Events;

use Polymorph\Platform\Domain\Routing\Core\Models\RouteNode;

/**
 * Доменное событие: узел маршрута обновлён.
 */
final class RouteNodeUpdated
{
    public function __construct(
        public readonly RouteNode $node,
    ) {}
}
