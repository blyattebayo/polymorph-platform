<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Data;

use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\ValidationRules;

final readonly class UpdateFieldData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     * @param  array<string, mixed>|null  $constraints
     */
    public function __construct(
        public int $fieldId,
        public ?string $name,
        public FieldParentChange $parentChange,
        public bool $validationRulesChanged,
        public ?ValidationRules $validationRules,
        public bool $isIndexedChanged,
        public bool $isIndexed,
        public bool $sortOrderChanged,
        public int $sortOrder,
        public bool $metadataChanged,
        public mixed $metadata,
        public bool $constraintsChanged,
        public ?array $constraints,
    ) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public static function fromArray(array $item): self
    {
        $fieldId = (int) ($item['id'] ?? 0);

        $name = null;
        if (array_key_exists('name', $item)) {
            $name = trim((string) $item['name']);
        }

        $parentChange = FieldParentChange::unchanged();
        if (array_key_exists('parent_id', $item)) {
            if ($item['parent_id'] === null) {
                $parentChange = FieldParentChange::toRoot();
            } else {
                $parentId = (int) $item['parent_id'];
                $parentChange = FieldParentChange::to($parentId);
            }
        }

        $validationRulesChanged = array_key_exists('validation_rules', $item);
        $validationRules = null;
        if ($validationRulesChanged && is_array($item['validation_rules'])) {
            $validationRules = ValidationRules::fromArray($item['validation_rules']);
        }

        return new self(
            fieldId: $fieldId,
            name: $name,
            parentChange: $parentChange,
            validationRulesChanged: $validationRulesChanged,
            validationRules: $validationRules,
            isIndexedChanged: array_key_exists('is_indexed', $item),
            isIndexed: (bool) ($item['is_indexed'] ?? false),
            sortOrderChanged: array_key_exists('sort_order', $item),
            sortOrder: (int) ($item['sort_order'] ?? 0),
            metadataChanged: array_key_exists('metadata', $item),
            metadata: $item['metadata'] ?? null,
            constraintsChanged: array_key_exists('constraints', $item),
            constraints: is_array($item['constraints'] ?? null) ? $item['constraints'] : null,
        );
    }
}
