<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Resolvers;

use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;

/**
 * Резолвер для VIEW действий.
 */
class ViewActionResolver implements ActionResolverInterface
{
    public function resolve(RouteNodeDefinition $node): callable
    {
        $view = $node->actionMeta['view'] ?? null;
        $data = $node->actionMeta['data'] ?? [];

        return $view ? fn () => view($view, $data) : fn () => abort(404);
    }
}
