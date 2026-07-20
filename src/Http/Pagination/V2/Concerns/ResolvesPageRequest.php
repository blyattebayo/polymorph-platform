<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Pagination\V2\Concerns;

use Polymorph\Platform\SharedKernel\Pagination\V2\PageLimits;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;

trait ResolvesPageRequest
{
    public function pageRequest(?PageLimits $limits = null): PageRequest
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return PageRequest::fromValidated($validated, $limits);
    }
}

