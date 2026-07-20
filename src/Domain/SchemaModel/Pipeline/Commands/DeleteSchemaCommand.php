<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Commands;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;

final class DeleteSchemaCommand
{
    public function __construct(
        public readonly SchemaModel $schema,
        public readonly ?string $operationId = null,
    ) {}
}
