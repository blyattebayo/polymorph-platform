<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Schema;

use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Fields\Cardinality;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldName;

/** The sole compiler for parent/name structure, materialized paths and traversal metadata. */
final class SchemaCompiler
{
    /** @param list<FieldDefinition> $fields */
    public function compile(array $fields): CompiledSchemaTree
    {
        $inputById = [];
        foreach ($fields as $field) {
            if (isset($inputById[$field->id])) {
                throw DataPlatformBadRequest::because(
                    'duplicate_field_id',
                    "Duplicate stable field id '{$field->id}'.",
                    ['field_id' => $field->id],
                );
            }
            FieldName::from($field->name);
            $inputById[$field->id] = $field;
        }

        $compiled = [];
        $visiting = [];
        $compile = function (FieldDefinition $field) use (&$compile, &$compiled, &$visiting, $inputById): FieldDefinition {
            if (isset($compiled[$field->id])) {
                return $compiled[$field->id];
            }
            if (isset($visiting[$field->id])) {
                throw DataPlatformBadRequest::because(
                    'cyclic_field_parent',
                    "Field '{$field->name}' has a cyclic parent chain.",
                    ['field_id' => $field->id],
                );
            }
            $visiting[$field->id] = true;

            $parent = null;
            if ($field->parentId !== null) {
                $parentInput = $inputById[$field->parentId] ?? null;
                if (! $parentInput instanceof FieldDefinition || $parentInput->id === $field->id) {
                    throw DataPlatformBadRequest::because(
                        'invalid_parent_field',
                        "Parent field '{$field->parentId}' must exist in the same schema version.",
                        ['field_id' => $field->id, 'parent_field_id' => $field->parentId],
                    );
                }
                $parent = $compile($parentInput);
                if ($parent->typeName() !== 'json') {
                    throw DataPlatformBadRequest::because(
                        'scalar_field_has_children',
                        "Field '{$field->name}' requires a direct JSON container parent.",
                        ['field_id' => $field->id, 'parent_field_id' => $parent->id],
                    );
                }
            }

            $path = $parent instanceof FieldDefinition ? $parent->path.'.'.$field->name : $field->name;
            if ($field->path !== '' && $field->path !== $path) {
                throw DataPlatformBadRequest::because(
                    'materialized_field_path_mismatch',
                    "Stored path '{$field->path}' does not match server-computed path '{$path}'.",
                    ['field_id' => $field->id, 'stored_path' => $field->path, 'computed_path' => $path],
                );
            }
            $multiValued = $field->cardinality === Cardinality::MANY
                || ($parent instanceof FieldDefinition && $parent->multiValued);
            $collectionPaths = $parent?->collectionPaths ?? [];
            if ($field->cardinality === Cardinality::MANY) {
                $collectionPaths[] = $path;
            }
            $compiled[$field->id] = new FieldDefinition(
                id: $field->id,
                path: $path,
                name: $field->name,
                type: $field->type,
                cardinality: $field->cardinality,
                system: $field->system,
                projectionVersion: $field->projectionVersion,
                constraints: $field->constraints,
                metadata: $field->metadata,
                parentId: $field->parentId,
                multiValued: $multiValued,
                position: $field->position,
                collectionPaths: $collectionPaths,
            );
            unset($visiting[$field->id]);

            return $compiled[$field->id];
        };

        foreach ($fields as $field) {
            $compile($field);
        }
        $paths = [];
        $siblingNames = [];
        foreach ($compiled as $field) {
            if (isset($paths[$field->path])) {
                throw DataPlatformBadRequest::because(
                    'duplicate_field_path',
                    "Duplicate field path '{$field->path}'.",
                    ['path' => $field->path],
                );
            }
            $siblingKey = ($field->parentId ?? '$').'\0'.$field->name;
            if (isset($siblingNames[$siblingKey])) {
                throw DataPlatformBadRequest::because(
                    'duplicate_sibling_field_name',
                    "Duplicate sibling field name '{$field->name}'.",
                    ['name' => $field->name, 'parent_field_id' => $field->parentId],
                );
            }
            $paths[$field->path] = true;
            $siblingNames[$siblingKey] = true;
        }

        return new CompiledSchemaTree(array_values($compiled));
    }
}
