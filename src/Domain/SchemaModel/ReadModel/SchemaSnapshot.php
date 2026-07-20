<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\ReadModel;

readonly class SchemaSnapshot
{
    /**
     * @param  array<int, SchemaFieldSnapshot>  $fieldsById
     */
    public function __construct(
        public int $rootRecordDefinitionId,
        public array $fieldsById,
        public string $fullSchemaHash
    ) {}
}
