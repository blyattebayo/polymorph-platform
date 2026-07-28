<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Services;

use Illuminate\Support\Collection;
use Polymorph\Platform\Domain\Routing\Core\Enums\OwnerType;
use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;
use Polymorph\Platform\Domain\Routing\Infrastructure\Repositories\RouteTreeSource;

/**
 * Объединяет деревья маршрутов из всех источников в единое дерево.
 *
 * Зависит от списка RouteTreeSource (а не от конкретных репозиториев): добавить
 * новый источник = реализовать интерфейс и добавить его в список в провайдере,
 * без правки этого сервиса. Итоговый порядок задаётся приоритетом владельца
 * узла (system → plugin → client), поэтому от порядка источников не зависит.
 */
final class MergedRouteTreeService
{
    /**
     * @param  iterable<RouteTreeSource>  $sources
     */
    public function __construct(
        private readonly iterable $sources,
    ) {}

    /**
     * Собрать включённые деревья всех источников и отсортировать
     * по приоритету владельца.
     */
    public function getEnabledTree(): Collection
    {
        $merged = new Collection;
        foreach ($this->sources as $source) {
            $merged = $merged->concat($source->getEnabledTree());
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
        // Нераспознанный owner не роняет boot(), а уезжает в конец очереди.
        return match ($node->owner === null ? OwnerType::CLIENT : OwnerType::typeFrom($node->owner)) {
            OwnerType::SYSTEM => 1,
            OwnerType::PLUGIN => 2,
            OwnerType::CLIENT => 3,
            default => 999,
        };
    }
}
