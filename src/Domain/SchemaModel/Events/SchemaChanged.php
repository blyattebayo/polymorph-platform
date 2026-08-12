<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Events;

final readonly class SchemaChanged
{
    public function __construct(public int $schemaId) {}
}
