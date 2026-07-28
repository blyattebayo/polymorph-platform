<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Repositories;

use Illuminate\Support\Collection;
use Polymorph\Platform\Domain\Routing\Infrastructure\Loaders\SystemRouteLoader;
use Polymorph\Platform\Domain\Routing\Services\Cache\RouteCache;

final class SystemRouteRepository implements RouteTreeSource
{
    public function __construct(
        private SystemRouteLoader $loader,
        private RouteCache $cache,
    ) {}

    public function getTree(): Collection
    {
        return $this->cache->rememberSystemTree(
            enabledOnly: false,
            callback: fn () => $this->loader->loadAll()
        );
    }

    public function getEnabledTree(): Collection
    {
        return $this->cache->rememberSystemTree(
            enabledOnly: true,
            callback: fn () => $this->loader->loadAll()->filter(fn ($node) => $node->enabled)->values()
        );
    }
}
