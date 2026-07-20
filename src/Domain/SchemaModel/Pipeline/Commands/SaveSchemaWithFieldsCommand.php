<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Commands;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;

final class SaveSchemaWithFieldsCommand
{
    public function __construct(
        public readonly array $schemaPayload,
        public readonly array $fieldsUpsert,
        public readonly array $fieldsDelete,
        public readonly ?SchemaModel $schema = null,
        public readonly ?string $operationId = null,
    ) {}
}
