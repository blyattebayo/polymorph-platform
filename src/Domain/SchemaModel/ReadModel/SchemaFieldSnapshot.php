<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\ReadModel;

readonly class SchemaFieldSnapshot
{
    public function __construct(
        public int $id,
        public string $name,
        public string $type,
        public string $cardinality,
        public string $dataPath,
        public string $fullPath,
        public ?int $parentId,
        public ?int $recordDefinitionId = null,
        public ?int $allowedRecordDefinitionId = null,
    ) {
    }

    public function isRef(): bool
    {
        return $this->type === 'ref';
    }
}
