<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Core\Query;

final readonly class RecordQueryCondition
{
    public function __construct(
        public string $key,
        public string $type,
        public ?string $cast,
        public string $op,
        public mixed $value,
        public RecordQueryStrategy $strategy = RecordQueryStrategy::Expression,
    ) {}
}
