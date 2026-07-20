<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Services;

use Polymorph\Platform\Domain\Routing\Core\Exceptions\CyclicDependencyException;
use Polymorph\Platform\Domain\Routing\Core\Models\RouteNode;
use Illuminate\Support\Collection;

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
     * @param array<int, array{id: int, parent_id?: int|null}> $nodes
     * @param Collection<int, RouteNode> $existing
     *
     * @throws CyclicDependencyException
     */
    public function assertReorderHasNoCycles(array $nodes, Collection $existing): void
    {
        $graph = [];

        foreach ($nodes as $nodeData) {
            $id = $nodeData['id'];
            $parentId = $nodeData['parent_id'] ?? null;
            $graph[$id] = $parentId;
        }

        foreach ($existing as $node) {
            if (! isset($graph[$node->id])) {
                $graph[$node->id] = $node->parent_id;
            }
        }

        foreach ($nodes as $nodeData) {
            $this->assertNoCycleInGraph($nodeData['id'], $graph);
        }
    }

    /**
     * @param array<int, int|null> $graph
     *
     * @throws CyclicDependencyException
     */
    private function assertNoCycleInGraph(int $nodeId, array $graph): void
    {
        $visited = [];
        $current = $nodeId;

        while ($current !== null) {
            if (in_array($current, $visited, true)) {
                throw new CyclicDependencyException("Cycle detected involving node #{$nodeId}");
            }

            $visited[] = $current;
            $current = $graph[$current] ?? null;
        }
    }
}
