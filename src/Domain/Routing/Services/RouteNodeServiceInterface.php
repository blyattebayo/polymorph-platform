<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Services;

use Polymorph\Platform\Domain\Routing\Core\Exceptions\CyclicDependencyException;
use Polymorph\Platform\Domain\Routing\Core\Exceptions\ReadonlyRouteNodeException;
use Polymorph\Platform\Domain\Routing\Core\Exceptions\RouteNodeConflictException;
use Polymorph\Platform\Domain\Routing\Core\Exceptions\RouteNodeNotFoundException;
use Polymorph\Platform\Domain\Routing\Core\Models\RouteNode;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

interface RouteNodeServiceInterface
{
    /**
     * Создать новый узел маршрута.
     * 
     * @param array $data
     * @return RouteNode
     * @throws ValidationException
     * @throws RouteNodeConflictException
     */
    public function create(array $data): RouteNode;
    
    /**
     * Обновить существующий узел маршрута.
     * 
     * @param int $id
     * @param array $data
     * @return RouteNode
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
     * @param int $id
     * @return int
     * @throws RouteNodeNotFoundException
     * @throws ReadonlyRouteNodeException
     */
    public function delete(int $id): int;
    
    /**
     * Переупорядочить узлы маршрутов.
     * 
     * @param array<array{id: int, parent_id: ?int, sort_order: int}> $nodes
     * @return int
     * @throws ValidationException
     * @throws CyclicDependencyException
     */
    public function reorder(array $nodes): int;
    
    /**
     * Получить иерархическое дерево всех маршрутов.
     * 
     * @param bool $enabledOnly
     * @return Collection
     */
    public function getTree(bool $enabledOnly = true): Collection;
    
    /**
     * Восстановить удалённый узел с детьми.
     * 
     * @param int $id
     * @return void
     */
    public function restoreWithChildren(int $id): void;
}
