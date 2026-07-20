<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Resolvers;

use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;

/**
 * Резолвер для REDIRECT действий.
 */
class RedirectActionResolver implements ActionResolverInterface
{
    public function resolve(RouteNodeDefinition $node): callable
    {
        $to = $node->actionMeta['to'] ?? null;
        $status = $node->actionMeta['status'] ?? 302;

        return $to ? fn() => redirect($to, $status) : fn() => abort(404);
    }
}
