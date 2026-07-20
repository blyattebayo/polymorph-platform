<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Schema;

use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;
use Polymorph\Platform\Domain\SchemaModelValidation\FieldPathBuilder;

final class SchemaDescriptorFactory
{
    public function __construct(
        private readonly FieldPathBuilder $pathBuilder,
    ) {
    }

    /**
     * @param iterable<object> $rows
     */
    public function fromFieldRows(int $schemaId, iterable $rows): SchemaDescriptor
    {
        $rows = is_array($rows) ? $rows : iterator_to_array($rows, false);

        $pathCardinalities = [];
        foreach ($rows as $row) {
            $pathCardinalities[(string) $row->full_path] = (string) $row->cardinality->value;
        }

        $fields = [];
        $pathIndex = [];

        foreach ($rows as $row) {
            $fullPath = (string) $row->full_path;
            $dataPath = $this->pathBuilder->computeDataPath(
                $fullPath,
                (string) $row->cardinality->value,
                $pathCardinalities,
                $row->type === FieldType::JSON,
            );
            $validationRules = $row->validation_rules?->toArray() ?? [];

            $fields[] = new FieldDescriptor(
                fullPath: $fullPath,
                dataPath: $dataPath,
                type: $row->type,
                cardinality: $row->cardinality->value,
                isSystem: (bool) $row->is_system,
                validationRules: $validationRules,
            );

            $pathIndex[$fullPath] = $this->normalizeDataPathForIndex($dataPath);
        }

        return new SchemaDescriptor(
            schemaId: $schemaId,
            fields: $fields,
            pathIndex: $pathIndex,
        );
    }

    private function normalizeDataPathForIndex(string $dataPath): string
    {
        return str_ends_with($dataPath, '.*')
            ? substr($dataPath, 0, -2)
            : $dataPath;
    }
}
