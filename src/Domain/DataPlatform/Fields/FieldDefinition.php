<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields;

final readonly class FieldDefinition
{
    /** Built-ins are enums; plugin-defined types retain their registered string name. */
    public FieldType|string $type;

    /**
     * @param  array<string, mixed>  $constraints
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $path,
        public string $name,
        FieldType|string $type,
        public Cardinality $cardinality,
        public bool $system,
        public int $projectionVersion,
        public array $constraints = [],
        public array $metadata = [],
        public ?string $parentId = null,
        public bool $multiValued = false,
        public int $position = 0,
    ) {
        $this->type = is_string($type) ? (FieldType::tryFrom($type) ?? $type) : $type;
    }

    public function typeName(): string
    {
        return $this->type instanceof FieldType ? $this->type->value : $this->type;
    }
}
