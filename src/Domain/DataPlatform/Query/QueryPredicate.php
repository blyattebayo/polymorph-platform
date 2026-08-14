<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Query;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;

final readonly class QueryPredicate
{
    public function __construct(
        public FieldDefinition $field,
        public string $operator,
        public mixed $value,
    ) {}
}
