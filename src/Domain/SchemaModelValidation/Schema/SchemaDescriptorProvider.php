<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Schema;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;

final class SchemaDescriptorProvider
{
    public function __construct(
        private readonly SchemaDescriptorFactory $factory,
    ) {}

    public function forSchemaId(int $schemaId): SchemaDescriptor
    {
        $rows = Field::query()
            ->where('schema_id', $schemaId)
            ->select(['full_path', 'type', 'cardinality', 'is_system', 'validation_rules'])
            ->orderByRaw('LENGTH(full_path), full_path')
            ->get();

        return $this->factory->fromFieldRows($schemaId, $rows);
    }
}
