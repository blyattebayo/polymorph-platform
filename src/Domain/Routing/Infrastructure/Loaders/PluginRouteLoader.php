<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Loaders;

use Polymorph\Platform\Domain\Routing\Core\Contracts\PluginRouteCatalog;
use Polymorph\Platform\Domain\Routing\Core\Enums\OwnerType;
use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Illuminate\Support\Collection;

/**
 * Загрузчик declarative-маршрутов плагинов (в памяти, без записи в БД).
 *
 * Источник данных — порт {@see PluginRouteCatalog} (реализуется адаптером в
 * домене Plugins). Сам загрузчик знает только про сборку RouteNodeDefinition из конфига,
 * не про discovery/registry плагинов — направление зависимости инвертировано.
 *
 * Так как ничего не персистится, исчезают два класса проблем БД-режима:
 * не нужна инвалидация кэша роутов при enable/disable и не бывает
 * «осиротевших» route_nodes при удалённом с диска плагине.
 */
final class PluginRouteLoader
{
    public function __construct(
        private readonly PluginRouteCatalog $catalog,
        private readonly RouteDefinitionLoader $loader,
        private readonly AppLogger $logger,
    ) {}

    /**
     * Построить коллекцию верхних узлов-групп для всех включённых плагинов.
     *
     * @return Collection<int, RouteNodeDefinition>
     */
    public function loadAll(): Collection
    {
        $nodes = new Collection;

        foreach ($this->catalog->enabledRouteSets() as $set) {
            try {
                foreach ($this->buildNodes($set->pluginId, $set->config) as $node) {
                    $nodes->push($node);
                }
            } catch (\Throwable $exception) {
                // Резилентность: кривые роуты одного плагина не должны ронять
                // весь роутер. Плагин просто не попадёт в дерево.
                $this->logger
                    ->withContext(['plugin_id' => $set->pluginId])
                    ->error('routing.plugin.load_failed', ['exception' => $exception->getMessage()]);
            }
        }

        return $nodes;
    }

    /**
     * Fingerprint включённого набора плагинов — ротирует ключ кэша дерева
     * на всех воркерах при enable/disable/правке routes.php.
     */
    public function fingerprint(): string
    {
        return $this->catalog->fingerprint();
    }

    /**
     * Построить in-memory узлы-группы одного плагина (с проставленным owner).
     *
     * Используется и при сборке общего дерева (loadAll), и при вживлении
     * роутов в текущий роутер сразу после enable/update.
     *
     * @param list<array<string, mixed>> $config
     * @return Collection<int, RouteNodeDefinition>
     */
    public function buildNodes(string $pluginId, array $config): Collection
    {
        $nodes = new Collection;
        $sortOrder = 0;

        foreach ($config as $top) {
            if (! is_array($top)) {
                continue;
            }

            $node = $this->loader->createFromArray($top);
            if ($node === null) {
                continue;
            }

            $nodes->push(
                $node->withOwnership(
                    OwnerType::make(OwnerType::PLUGIN, $pluginId),
                    $sortOrder++
                )
            );
        }

        return $nodes;
    }
}
