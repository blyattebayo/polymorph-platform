<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Resolvers;

use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;

/**
 * Резолвер действий маршрутов.
 */
interface ActionResolverInterface
{
    /**
     * Преобразует описание узла в действие для Laravel Router.
     *
     * @return callable|string|array<string>|null
     */
    public function resolve(RouteNodeDefinition $node): callable|string|array|null;
}
