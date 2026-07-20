<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Repositories;

use Illuminate\Support\Collection;
use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;

/**
 * Источник дерева маршрутов для объединения в общий роутер.
 *
 * Единый контракт для всех источников (system из файлов, plugin declarative,
 * client из БД). MergedRouteTreeService зависит от списка таких источников,
 * а не от конкретных репозиториев — добавление нового источника не требует
 * правки сервиса (Open/Closed).
 */
interface RouteTreeSource
{
    /**
     * Полное дерево корневых узлов источника (включая выключенные).
     *
     * @return Collection<int, RouteNodeDefinition>
     */
    public function getTree(): Collection;

    /**
     * Дерево только включённых корневых узлов источника.
     *
     * @return Collection<int, RouteNodeDefinition>
     */
    public function getEnabledTree(): Collection;
}
