<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Polymorph\Platform\Domain\Routing\Core\Exceptions\CyclicDependencyException;
use Polymorph\Platform\Domain\Routing\Core\Exceptions\ReadonlyRouteNodeException;
use Polymorph\Platform\Domain\Routing\Core\Exceptions\RouteNodeConflictException;
use Polymorph\Platform\Domain\Routing\Core\Exceptions\RouteNodeNotFoundException;
use Polymorph\Platform\Domain\Routing\Core\Models\RouteNode;

interface RouteNodeServiceInterface
{
    /**
     * Создать новый узел маршрута.
     *
     * @throws ValidationException
     * @throws RouteNodeConflictException
     */
    public function create(array $data): RouteNode;

    /**
     * Обновить существующий узел маршрута.
     *
     * @throws RouteNodeNotFoundException
     * @throws ValidationException
     * @throws RouteNodeConflictException
     * @throws ReadonlyRouteNodeException
     */
    public function update(int $id, array $data): RouteNode;

    /**
     * Получить узел маршрута по ID (с загруженными parent/children).
     *
     * @throws RouteNodeNotFoundException
     */
    public function getById(int $id): RouteNode;

    /**
     * Удалить узел маршрута (каскадное).
     *
     * @throws RouteNodeNotFoundException
     * @throws ReadonlyRouteNodeException
     */
    public function delete(int $id): int;

    /**
     * Переупорядочить узлы маршрутов.
     *
     * @param  array<array{id: int, parent_id: ?int, sort_order: int}>  $nodes
     *
     * @throws ValidationException
     * @throws CyclicDependencyException
     */
    public function reorder(array $nodes): int;

    /**
     * Получить иерархическое дерево всех маршрутов.
     */
    public function getTree(bool $enabledOnly = true): Collection;

    /**
     * Восстановить удалённый узел с детьми.
     */
    public function restoreWithChildren(int $id): void;
}
