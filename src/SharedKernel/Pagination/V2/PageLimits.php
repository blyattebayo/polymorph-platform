<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Pagination\V2;

final readonly class PageLimits
{
    public function __construct(
        public int $defaultPage = 1,
        public int $defaultPerPage = 15,
        public int $minPerPage = 1,
        public int $maxPerPage = 100,
    ) {}
}
