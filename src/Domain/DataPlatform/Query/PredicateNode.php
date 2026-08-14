<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Query;

final readonly class PredicateNode implements FilterNode
{
    public function __construct(
        public string $field,
        public string $operator,
        public mixed $value = null,
    ) {}
}
