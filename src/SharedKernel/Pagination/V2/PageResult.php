<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Pagination\V2;

final readonly class PageResult
{
    /**
     * @param  list<mixed>  $items
     */
    public function __construct(
        public array $items,
        public PageMeta $meta,
    ) {}

    /**
     * @param  callable(mixed): mixed  $mapper
     */
    public function mapItems(callable $mapper): self
    {
        return new self(
            items: array_values(array_map($mapper, $this->items)),
            meta: $this->meta,
        );
    }

    /**
     * @param  list<mixed>  $items
     */
    public function withItems(array $items): self
    {
        return new self(
            items: array_values($items),
            meta: $this->meta,
        );
    }

    /**
     * @param  callable(mixed): int  $idResolver
     * @return list<int>
     */
    public function ids(callable $idResolver): array
    {
        return array_values(array_map($idResolver, $this->items));
    }

    /**
     * @return array{data: list<mixed>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'meta' => $this->meta->toArray(),
        ];
    }
}
