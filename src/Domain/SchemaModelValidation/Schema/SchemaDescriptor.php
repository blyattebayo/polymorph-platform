<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Schema;

final readonly class SchemaDescriptor
{
    /**
     * @param  list<FieldDescriptor>  $fields
     * @param  array<string, string>  $pathIndex
     */
    public function __construct(
        public int $schemaId,
        public array $fields,
        public array $pathIndex,
    ) {}

    public function dataPathByFullPath(string $fullPath): ?string
    {
        return $this->pathIndex[$fullPath] ?? null;
    }
}
