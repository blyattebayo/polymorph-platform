<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Services;

use Polymorph\Platform\Domain\Routing\Core\Enums\OwnerType;
use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;
use Polymorph\Platform\Domain\Routing\Infrastructure\Repositories\RouteTreeSource;
use Illuminate\Support\Collection;

/**
 * Объединяет деревья маршрутов из всех источников в единое дерево.
 *
 * Зависит от списка RouteTreeSource (а не от конкретных репозиториев): добавить
 * новый источник = реализовать интерфейс и добавить его в список в провайдере,
 * без правки этого сервиса. Итоговый порядок задаётся приоритетом владельца
 * узла (system → plugin → client), поэтому от порядка источников не зависит.
 *
 * @package Polymorph\Platform\Domain\Routing\Services
 */
final class MergedRouteTreeService
{
    /**
     * @param iterable<RouteTreeSource> $sources
     */
    public function __construct(
        private readonly iterable $sources,
    ) {}

    public function getTree(): Collection
    {
        return $this->mergeAndSort(static fn (RouteTreeSource $source): Collection => $source->getTree());
    }

    public function getEnabledTree(): Collection
    {
        return $this->mergeAndSort(static fn (RouteTreeSource $source): Collection => $source->getEnabledTree());
    }

    /**
     * Собрать деревья всех источников и отсортировать по приоритету владельца.
     *
     * @param callable(RouteTreeSource): Collection $extract
     */
    private function mergeAndSort(callable $extract): Collection
    {
        $merged = new Collection;
        foreach ($this->sources as $source) {
            $merged = $merged->concat($extract($source));
        }

        return $merged
            ->sort(function (RouteNodeDefinition $a, RouteNodeDefinition $b): int {
                return [$this->priority($a), $a->sortOrder, $a->sourceId ?? 0]
                    <=> [$this->priority($b), $b->sortOrder, $b->sourceId ?? 0];
            })
            ->values();
    }

    private function priority(RouteNodeDefinition $node): int
    {
        $ownerType = $node->owner
            ? OwnerType::parse($node->owner)['type']
            : OwnerType::CLIENT;

        return match ($ownerType) {
            OwnerType::SYSTEM => 1,
            OwnerType::PLUGIN => 2,
            OwnerType::CLIENT => 3,
            default => 999,
        };
    }
}
