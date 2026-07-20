<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Polymorph\Platform\Domain\Routing\Core\Enums\OwnerType;
use Polymorph\Platform\Domain\Routing\Core\Models\RouteNode;
use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;
use Polymorph\Platform\Domain\Routing\Services\Cache\RouteCache;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

final class ClientRouteRepository implements RouteTreeSource
{
    public function __construct(
        private RouteCache $cache,
        private readonly AppLogger $logger,
    ) {}

    public function getTree(): Collection
    {
        return $this->cache->rememberClientTree(
            enabledOnly: false,
            callback: fn () => $this->mapToDefinitions(
                $this->buildTreeFromDatabase(enabledOnly: false, catchDbErrors: false)
            )
        );
    }

    public function getEnabledTree(): Collection
    {
        return $this->cache->rememberClientTree(
            enabledOnly: true,
            callback: fn () => $this->mapToDefinitions(
                $this->buildTreeFromDatabase(enabledOnly: true)
            )
        );
    }

    public function eloquentTree(bool $enabledOnly): EloquentCollection
    {
        return $this->buildTreeFromDatabase($enabledOnly);
    }

    public function deleteSubtree(RouteNode $node): int
    {
        $count = 0;

        $children = RouteNode::query()
            ->where('parent_id', $node->id)
            ->get();

        foreach ($children as $child) {
            /** @var RouteNode $child */
            $count += $this->deleteSubtree($child);
        }

        $node->delete();

        return $count + 1;
    }

    public function restoreSubtree(RouteNode $node): void
    {
        $node->restore();

        $children = RouteNode::onlyTrashed()
            ->where('parent_id', $node->id)
            ->get();

        foreach ($children as $child) {
            /** @var RouteNode $child */
            $this->restoreSubtree($child);
        }
    }

    private function buildTreeFromDatabase(bool $enabledOnly, bool $catchDbErrors = true): EloquentCollection
    {
        $dbNodes = $this->loadNodesFromDatabase($enabledOnly, $catchDbErrors);

        return $this->buildTreeFromNodes($dbNodes);
    }

    private function loadNodesFromDatabase(bool $enabledOnly, bool $catchDbErrors = true): EloquentCollection
    {
        $query = RouteNode::query()
            ->where('owner', 'like', OwnerType::CLIENT->value.'%')
            ->when($enabledOnly, fn ($q) => $q->where('enabled', true))
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');

        if (! $catchDbErrors) {
            return $query->get();
        }

        try {
            return $query->get();
        } catch (\Throwable $e) {
            // Не теряем роуты молча: фиксируем сбой загрузки дерева в лог.
            $this->logger->error('routing.client_tree.load_failed', [
                'error' => $e->getMessage(),
            ]);

            return new EloquentCollection;
        }
    }

    private function buildTreeFromNodes(EloquentCollection $nodes): EloquentCollection
    {
        $byId = $nodes->keyBy('id');
        $roots = new EloquentCollection;

        foreach ($nodes as $node) {
            if ($node->parent_id === null) {
                $roots->push($node);

                continue;
            }

            $parent = $byId->get($node->parent_id);
            if (! $parent) {
                continue;
            }

            if (! $parent->relationLoaded('children')) {
                $parent->setRelation('children', new EloquentCollection);
            }

            $parent->children->push($node);
        }

        return $roots;
    }

    private function mapToDefinitions(EloquentCollection $nodes): Collection
    {
        return $nodes
            ->map(
                static fn (RouteNode $node): RouteNodeDefinition => RouteNodeDefinition::fromModel($node)
            )
            ->values();
    }
}
