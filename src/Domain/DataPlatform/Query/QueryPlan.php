<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Query;

use Illuminate\Database\Query\Builder;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;

final readonly class QueryPlan
{
    /**
     * @param  list<string>  $strategies
     * @param  list<string>  $warnings
     * @param  array<string,FieldDefinition>  $fields
     */
    public function __construct(
        public Builder $builder,
        public array $strategies,
        public array $warnings,
        public array $fields,
    ) {}
}
