<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\TableConfig\Services;

final class TableConfigMergeService
{
    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    public function merge(array $base, array $override): array
    {
        return array_replace_recursive($base, $override);
    }
}
