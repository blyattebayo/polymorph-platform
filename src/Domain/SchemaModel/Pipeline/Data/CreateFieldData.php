<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Data;

use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\Cardinality;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\ValidationRules;

final readonly class CreateFieldData
{
    /**
     * @param array<string, mixed>|null $metadata
     * @param array<string, mixed>|null $constraints
     */
    public function __construct(
        public string $name,
        public FieldType $type,
        public Cardinality $cardinality,
        public ?int $parentId,
        public bool $isIndexed,
        public int $sortOrder,
        public ?ValidationRules $validationRules,
        public ?array $metadata,
        public bool $constraintsProvided,
        public ?array $constraints,
    ) {}

    /**
     * @param array<string, mixed> $item
     */
    public static function fromArray(array $item): self
    {
        $name = trim((string) ($item['name'] ?? ''));

        $type = self::normalizeFieldType($item['type'] ?? null) ?? FieldType::STRING;

        $cardinality = self::normalizeCardinality($item['cardinality'] ?? null) ?? Cardinality::ONE;

        $parentId = null;
        if (array_key_exists('parent_id', $item) && $item['parent_id'] !== null) {
            $parentId = (int) $item['parent_id'];
        }

        $validationRules = null;
        if (array_key_exists('validation_rules', $item) && is_array($item['validation_rules'])) {
            $validationRules = ValidationRules::fromArray($item['validation_rules']);
        }

        return new self(
            name: $name,
            type: $type,
            cardinality: $cardinality,
            parentId: $parentId,
            isIndexed: (bool) ($item['is_indexed'] ?? false),
            sortOrder: (int) ($item['sort_order'] ?? 0),
            validationRules: $validationRules,
            metadata: is_array($item['metadata'] ?? null) ? $item['metadata'] : null,
            constraintsProvided: array_key_exists('constraints', $item),
            constraints: is_array($item['constraints'] ?? null) ? $item['constraints'] : null,
        );
    }

    private static function normalizeFieldType(mixed $value): ?FieldType
    {
        if ($value instanceof FieldType) {
            return $value;
        }

        return is_string($value) ? FieldType::tryFrom($value) : null;
    }

    private static function normalizeCardinality(mixed $value): ?Cardinality
    {
        if ($value instanceof Cardinality) {
            return $value;
        }

        return is_string($value) ? Cardinality::tryFrom($value) : null;
    }
}
