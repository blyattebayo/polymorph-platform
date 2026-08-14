<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Control;

use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Fields\Cardinality;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldName;

/** One node in the canonical nested control-plane schema contract. */
final readonly class FieldSpecification
{
    /**
     * @param  array<string,mixed>  $constraints
     * @param  array<string,mixed>  $metadata
     * @param  list<FieldSpecification>  $children
     */
    public function __construct(
        public ?string $fieldId,
        public string $name,
        public string $type,
        public Cardinality $cardinality,
        public bool $system,
        public int $position,
        public int $projectionVersion,
        public array $constraints,
        public array $metadata,
        public array $children,
    ) {}

    /** @param array<string,mixed> $input */
    public static function fromArray(array $input, int $defaultPosition = 0): self
    {
        foreach (['path', 'full_path'] as $forbidden) {
            if (array_key_exists($forbidden, $input)) {
                throw DataPlatformBadRequest::because(
                    'client_supplied_field_path',
                    'Field path is server-computed and cannot be supplied by a client.',
                    ['property' => $forbidden],
                );
            }
        }
        foreach (['parent_field_id', 'parent_id', 'key'] as $forbidden) {
            if (array_key_exists($forbidden, $input)) {
                throw DataPlatformBadRequest::because(
                    'flat_field_tree_contract_rejected',
                    "Field property '{$forbidden}' is not accepted; express structure with nested children.",
                    ['property' => $forbidden],
                );
            }
        }

        $name = FieldName::from((string) ($input['name'] ?? ''))->value;
        $type = trim((string) ($input['type'] ?? ''));
        if ($type === '') {
            throw DataPlatformBadRequest::because(
                'missing_field_type',
                "Field '{$name}' requires a type.",
                ['name' => $name],
            );
        }
        $cardinalityValue = trim((string) ($input['cardinality'] ?? Cardinality::ONE->value));
        $cardinality = Cardinality::tryFrom($cardinalityValue);
        if (! $cardinality instanceof Cardinality) {
            throw DataPlatformBadRequest::because(
                'invalid_field_cardinality',
                "Unsupported field cardinality '{$cardinalityValue}'.",
                ['name' => $name, 'cardinality' => $cardinalityValue],
            );
        }
        $fieldId = trim((string) ($input['field_id'] ?? ''));
        $childrenInput = $input['children'] ?? [];
        if (! is_array($childrenInput)) {
            throw DataPlatformBadRequest::because(
                'invalid_field_children',
                "Field '{$name}' children must be a list.",
                ['name' => $name],
            );
        }
        $children = [];
        foreach (array_values($childrenInput) as $index => $child) {
            if (! is_array($child)) {
                throw DataPlatformBadRequest::because(
                    'invalid_field_child',
                    "Field '{$name}' contains an invalid child specification.",
                    ['name' => $name, 'index' => $index],
                );
            }
            $children[] = self::fromArray($child, $index);
        }

        return new self(
            fieldId: $fieldId === '' ? null : $fieldId,
            name: $name,
            type: $type,
            cardinality: $cardinality,
            system: (bool) ($input['is_system'] ?? false),
            position: (int) ($input['position'] ?? $defaultPosition),
            projectionVersion: max(1, (int) ($input['projection_version'] ?? 1)),
            constraints: is_array($input['constraints'] ?? null) ? $input['constraints'] : [],
            metadata: is_array($input['metadata'] ?? null) ? $input['metadata'] : [],
            children: $children,
        );
    }

    public function toField(string $fieldId, ?string $parentFieldId): FieldDefinition
    {
        return new FieldDefinition(
            id: $fieldId,
            path: '',
            name: $this->name,
            type: $this->type,
            cardinality: $this->cardinality,
            system: $this->system,
            projectionVersion: $this->projectionVersion,
            constraints: $this->constraints,
            metadata: $this->metadata,
            parentId: $parentFieldId,
            position: $this->position,
        );
    }
}
