<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Services;

use Illuminate\Support\Collection;
use Polymorph\Platform\Domain\Routing\Core\Exceptions\CyclicDependencyException;
use Polymorph\Platform\Domain\Routing\Core\Models\RouteNode;

final class RouteCycleDetector
{
    /**
     * @throws CyclicDependencyException
     */
    public function assertNoCycle(int $nodeId, ?int $newParentId): void
    {
        if ($newParentId === null) {
            return;
        }

        $current = $newParentId;
        $visited = [];

        while ($current !== null) {
            if ($current === $nodeId) {
                throw new CyclicDependencyException(
                    "Cyclic dependency detected: node #{$nodeId} cannot be child of #{$newParentId}"
                );
            }

            if (in_array($current, $visited, true)) {
                throw new CyclicDependencyException('Existing cycle detected in tree');
            }

            $visited[] = $current;

            $parent = RouteNode::find($current);
            $current = $parent?->parent_id;
        }
    }

    /**
     * @param  array<int, array{id: int, parent_id?: int|null}>  $nodes
     * @param  Collection<int, RouteNode>  $existing
     *
     * @throws CyclicDependencyException
     */
    public function assertReorderHasNoCycles(array $nodes, Collection $existing): void
    {
        $existingById = $existing->keyBy('id');
        $graph = [];

        foreach ($nodes as $nodeData) {
            $id = $nodeData['id'];

            // Отсутствие ключа parent_id означает «родителя не меняем», а не
            // «переносим в корень» — reorder обновляет только присланные поля.
            $graph[$id] = array_key_exists('parent_id', $nodeData)
                ? $nodeData['parent_id']
                : $existingById->get($id)?->parent_id;
        }

        foreach ($existingById as $id => $node) {
            if (! array_key_exists($id, $graph)) {
                $graph[$id] = $node->parent_id;
            }
        }

        foreach ($nodes as $nodeData) {
            $this->assertNoCycleInGraph($nodeData['id'], $graph);
        }
    }

    /**
     * Пройти цепочку предков узла с учётом переносов из payload.
     *
     * Предки, которых нет в графе (их не двигают, поэтому клиент их не
     * присылает), дочитываются из БД и запоминаются. Без этого обход
     * обрывался на первом же таком узле и цикл через него не находился:
     * например перенос корня A под его же внука C при неизменных A←B←C.
     *
     * @param  array<int, int|null>  $graph
     *
     * @throws CyclicDependencyException
     */
    private function assertNoCycleInGraph(int $nodeId, array &$graph): void
    {
        $visited = [];
        $current = $nodeId;

        while ($current !== null) {
            if (in_array($current, $visited, true)) {
                throw new CyclicDependencyException("Cycle detected involving node #{$nodeId}");
            }

            $visited[] = $current;

            if (! array_key_exists($current, $graph)) {
                $graph[$current] = RouteNode::query()->whereKey($current)->value('parent_id');
            }

            $current = $graph[$current];
        }
    }
}
