<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Control;

use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Fields\Cardinality;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;

/** Typed boundary for one field submitted to the schema editor. */
final readonly class FieldSpecification
{
    /**
     * @param  array<string, mixed>  $constraints
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $fieldId,
        public string $key,
        public string $path,
        public string $name,
        public string $type,
        public Cardinality $cardinality,
        public bool $system,
        public int $position,
        public int $projectionVersion,
        public array $constraints,
        public array $metadata,
        public ?string $parentFieldId,
    ) {}

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input, int $defaultPosition = 0): self
    {
        $path = trim((string) ($input['path'] ?? $input['name'] ?? ''));
        $name = trim((string) ($input['name'] ?? basename(str_replace('.', '/', $path))));
        $cardinalityValue = trim((string) ($input['cardinality'] ?? Cardinality::ONE->value));
        $cardinality = Cardinality::tryFrom($cardinalityValue);
        if (! $cardinality instanceof Cardinality) {
            throw DataPlatformBadRequest::because(
                'invalid_field_cardinality',
                "Unsupported field cardinality '{$cardinalityValue}'.",
                ['path' => $path, 'cardinality' => $cardinalityValue],
            );
        }

        $fieldId = trim((string) ($input['field_id'] ?? ''));
        $parentFieldId = isset($input['parent_field_id']) && $input['parent_field_id'] !== null
            ? trim((string) $input['parent_field_id'])
            : null;

        return new self(
            fieldId: $fieldId === '' ? null : $fieldId,
            key: trim((string) ($input['key'] ?? $path)),
            path: $path,
            name: $name,
            type: trim((string) ($input['type'] ?? '')),
            cardinality: $cardinality,
            system: (bool) ($input['is_system'] ?? false),
            position: (int) ($input['position'] ?? $defaultPosition),
            projectionVersion: max(1, (int) ($input['projection_version'] ?? 1)),
            constraints: is_array($input['constraints'] ?? null) ? $input['constraints'] : [],
            metadata: is_array($input['metadata'] ?? null) ? $input['metadata'] : [],
            parentFieldId: $parentFieldId === '' ? null : $parentFieldId,
        );
    }

    public function toField(string $fieldId): FieldDefinition
    {
        return new FieldDefinition(
            id: $fieldId,
            path: $this->path,
            name: $this->name,
            type: $this->type,
            cardinality: $this->cardinality,
            system: $this->system,
            projectionVersion: $this->projectionVersion,
            constraints: $this->constraints,
            metadata: $this->metadata,
            parentId: $this->parentFieldId,
            position: $this->position,
        );
    }
}
