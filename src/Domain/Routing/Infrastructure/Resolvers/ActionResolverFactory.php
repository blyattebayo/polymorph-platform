<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Resolvers;

use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Фабрика резолверов действий маршрутов.
 */
class ActionResolverFactory
{
    private array $resolvers = [];

    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    /**
     * Установить резолверы.
     *
     * @param  array<string, ActionResolverInterface>  $resolvers
     */
    public function setResolvers(array $resolvers): void
    {
        $this->resolvers = $resolvers;
    }

    public function resolve(RouteNodeDefinition $node): callable|string|array|null
    {
        $actionType = $node->actionType?->value;
        $resolver = $actionType !== null ? ($this->resolvers[$actionType] ?? null) : null;

        if (! $resolver) {
            $this->logger->warning('routing.dynamic_route.resolver_missing', [
                'route_node_id' => $node->sourceId,
                'action_type' => $actionType,
            ]);

            return null;
        }

        return $resolver->resolve($node);
    }
}
