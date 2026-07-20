<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Contracts;

interface SchemaFieldPathReadModel
{
    /**
     * @return array{ref: string[], media: string[]}
     */
    public function schemaPaths(int $schemaId): array;

    /**
     * @param  int[]  $schemaIds
     * @return array<int, array{ref: string[], media: string[]}>
     */
    public function schemaPathsBySchemaIds(array $schemaIds): array;
}
