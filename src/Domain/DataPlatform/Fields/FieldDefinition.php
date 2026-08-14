<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields;

final readonly class FieldDefinition
{
    /**
     * @param  array<string, mixed>  $constraints
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $path,
        public string $name,
        public string $type,
        public Cardinality $cardinality,
        public bool $system,
        public int $projectionVersion,
        public array $constraints = [],
        public array $metadata = [],
        public ?string $parentId = null,
        public bool $multiValued = false,
        public int $position = 0,
    ) {}

}
