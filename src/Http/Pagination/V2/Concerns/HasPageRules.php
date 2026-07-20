<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Pagination\V2\Concerns;

use Polymorph\Platform\SharedKernel\Pagination\V2\PageLimits;

trait HasPageRules
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function pageRules(?PageLimits $limits = null): array
    {
        $limits ??= new PageLimits();

        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => [
                'nullable',
                'integer',
                'min:' . $limits->minPerPage,
                'max:' . $limits->maxPerPage,
            ],
        ];
    }
}

