<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\SystemFields;

use Polymorph\Platform\SharedKernel\SystemFields\SystemFieldNames;

final class SystemFieldPolicy
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function schemaFieldDefinitions(): array
    {
        return [
            [
                'name' => SystemFieldNames::CREATED_AT,
                'full_path' => SystemFieldNames::CREATED_AT,
                'type' => 'datetime',
                'cardinality' => 'one',
                'is_indexed' => true,
                'sort_order' => 10000,
                'metadata' => ['system_managed' => true],
            ],
            [
                'name' => SystemFieldNames::UPDATED_AT,
                'full_path' => SystemFieldNames::UPDATED_AT,
                'type' => 'datetime',
                'cardinality' => 'one',
                'is_indexed' => true,
                'sort_order' => 10001,
                'metadata' => ['system_managed' => true],
            ],
            [
                'name' => SystemFieldNames::DELETED_AT,
                'full_path' => SystemFieldNames::DELETED_AT,
                'type' => 'datetime',
                'cardinality' => 'one',
                'is_indexed' => true,
                'sort_order' => 10002,
                'metadata' => ['system_managed' => true],
            ],
        ];
    }
}
