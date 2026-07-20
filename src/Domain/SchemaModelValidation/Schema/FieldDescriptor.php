<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Schema;

use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;

final readonly class FieldDescriptor
{
    /**
     * @param array<string, mixed> $validationRules
     */
    public function __construct(
        public string $fullPath,
        public string $dataPath,
        public FieldType $type,
        public string $cardinality,
        public bool $isSystem,
        public array $validationRules,
    ) {}
}
