<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Control;

use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldTypeRegistry;
use Polymorph\Platform\Domain\DataPlatform\Serialization\CanonicalJson;

/** Validates complete schema snapshots and produces their canonical checksum. */
final class SchemaValidator
{
    public function __construct(
        private readonly FieldTypeRegistry $types,
        private readonly CanonicalJson $canonicalJson,
    ) {}

    /** @param list<FieldDefinition> $fields */
    public function validate(array $fields): string
    {
        if ($fields === []) {
            throw DataPlatformBadRequest::because('schema_has_no_fields', 'A schema cannot be validated without fields.');
        }

        $this->assertUniqueIdentityAndTree($fields);
        foreach ($fields as $field) {
            $this->types->get($field->type)->validateSchema($field);
        }

        $payloads = array_map($this->checksumPayload(...), $fields);
        usort($payloads, static fn (array $left, array $right): int => [
            $left['position'], $left['path'], $left['id'],
        ] <=> [
            $right['position'], $right['path'], $right['id'],
        ]);

        return $this->canonicalJson->hash($payloads);
    }

    /** @param list<FieldDefinition> $fields */
    public function assertUniqueIdentityAndTree(array $fields): void
    {
        $byId = [];
        $byPath = [];
        foreach ($fields as $field) {
            if ($field->path === '' || $field->name === '' || isset($byId[$field->id]) || isset($byPath[$field->path])) {
                throw DataPlatformBadRequest::because(
                    'duplicate_or_empty_field_identity',
                    "Duplicate or empty field identity '{$field->path}'.",
                    ['field_id' => $field->id, 'path' => $field->path],
                );
            }
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_-]*(?:\.[A-Za-z_][A-Za-z0-9_-]*)*$/D', $field->path) !== 1) {
                throw DataPlatformBadRequest::because(
                    'invalid_field_path',
                    "Invalid field path '{$field->path}'.",
                    ['path' => $field->path],
                );
            }
            $byId[$field->id] = $field;
            $byPath[$field->path] = $field;
        }

        foreach ($fields as $field) {
            if ($field->parentId === null) {
                continue;
            }
            $parent = $byId[$field->parentId] ?? null;
            if (! $parent instanceof FieldDefinition || $parent->id === $field->id) {
                throw DataPlatformBadRequest::because(
                    'invalid_parent_field',
                    "Parent field '{$field->parentId}' must exist in the same schema version.",
                    ['field_id' => $field->id, 'parent_field_id' => $field->parentId],
                );
            }
            if (! str_starts_with($field->path, $parent->path.'.')) {
                throw DataPlatformBadRequest::because(
                    'field_outside_parent_path',
                    "Field '{$field->path}' is outside its parent path '{$parent->path}'.",
                    ['field_id' => $field->id, 'path' => $field->path, 'parent_path' => $parent->path],
                );
            }

            $seen = [$field->id => true];
            $cursor = $parent;
            while ($cursor instanceof FieldDefinition) {
                if (isset($seen[$cursor->id])) {
                    throw DataPlatformBadRequest::because(
                        'cyclic_field_parent',
                        "Field '{$field->path}' has a cyclic parent chain.",
                        ['field_id' => $field->id, 'path' => $field->path],
                    );
                }
                $seen[$cursor->id] = true;
                $cursor = $cursor->parentId === null ? null : ($byId[$cursor->parentId] ?? null);
            }
        }
    }

    /** @return array<string, mixed> */
    private function checksumPayload(FieldDefinition $field): array
    {
        return [
            'id' => $field->id,
            'path' => $field->path,
            'type' => $field->typeName(),
            'cardinality' => $field->cardinality->value,
            'system' => $field->system,
            'projection_version' => $field->projectionVersion,
            'constraints' => $field->constraints,
            'metadata' => $field->metadata,
            'parent_id' => $field->parentId,
            'position' => $field->position,
        ];
    }
}
