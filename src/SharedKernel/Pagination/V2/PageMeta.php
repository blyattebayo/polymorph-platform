<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Pagination\V2;

final readonly class PageMeta
{
    public function __construct(
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
    ) {
    }

    /**
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage,
            'per_page' => $this->perPage,
            'total' => $this->total,
        ];
    }
}

