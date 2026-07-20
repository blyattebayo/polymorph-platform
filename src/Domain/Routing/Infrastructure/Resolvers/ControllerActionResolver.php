<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Resolvers;

use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Резолвер для CONTROLLER действий.
 */
class ControllerActionResolver implements ActionResolverInterface
{
    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    public function resolve(RouteNodeDefinition $node): callable|string|array|null
    {
        $action = $node->actionMeta['action'] ?? null;

        if (! $action) {
            $this->logger->warning('routing.dynamic_route.missing_action', ['route_node_id' => $node->sourceId]);

            return fn () => abort(404);
        }

        if (str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);

            return $this->validate($controller, $method) ? [$controller, $method] : fn () => abort(404);
        }

        return $this->validate($action) ? $action : fn () => abort(404);
    }

    private function validate(string $controller, ?string $method = null): bool
    {
        if (! class_exists($controller)) {
            $this->logger->error('routing.dynamic_route.controller_missing', ['controller' => $controller]);

            return false;
        }

        if ($method && ! method_exists($controller, $method)) {
            $this->logger->error('routing.dynamic_route.method_missing', ['controller' => $controller, 'method' => $method]);

            return false;
        }

        return true;
    }
}
