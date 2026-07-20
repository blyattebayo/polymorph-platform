<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Events\Contracts;

interface SchemaChangeEvent
{
    public function schemaId(): int;
}
