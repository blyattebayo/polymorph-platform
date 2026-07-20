<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Repositories;

use Polymorph\Platform\Domain\Routing\Infrastructure\Loaders\PluginRouteLoader;
use Polymorph\Platform\Domain\Routing\Services\Cache\RouteCache;
use Illuminate\Support\Collection;

/**
 * Репозиторий declarative-маршрутов плагинов.
 *
 * Кэширует дерево по fingerprint включённого набора плагинов: enable/disable
 * или правка routes.php ротируют ключ кэша автоматически на всех воркерах
 * (в отличие от db-режима, которому нужна явная инвалидация — корень P0-3).
 */
final class PluginRouteRepository implements RouteTreeSource
{
    public function __construct(
        private PluginRouteLoader $loader,
        private RouteCache $cache,
    ) {}

    public function getTree(): Collection
    {
        return $this->cache->rememberPluginTree(
            $this->loader->fingerprint(),
            fn () => $this->loader->loadAll(),
        );
    }

    public function getEnabledTree(): Collection
    {
        // Загрузчик уже отбирает только включённые плагины; узлы строятся
        // с enabled=true, но фильтруем для симметрии с другими репозиториями.
        return $this->cache->rememberPluginTree(
            $this->loader->fingerprint(),
            fn () => $this->loader->loadAll()->filter(fn ($node) => $node->enabled),
        );
    }

    public function loadFresh(): Collection
    {
        return $this->loader->loadAll();
    }
}
