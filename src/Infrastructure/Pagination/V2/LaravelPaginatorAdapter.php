<?php

declare(strict_types=1);

namespace Polymorph\Platform\Infrastructure\Pagination\V2;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageMeta;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageResult;

final class LaravelPaginatorAdapter
{
    public function toPageResult(LengthAwarePaginator $paginator): PageResult
    {
        return new PageResult(
            items: array_values($paginator->items()),
            meta: new PageMeta(
                currentPage: (int) $paginator->currentPage(),
                lastPage: (int) $paginator->lastPage(),
                perPage: (int) $paginator->perPage(),
                total: (int) $paginator->total(),
            ),
        );
    }
}
