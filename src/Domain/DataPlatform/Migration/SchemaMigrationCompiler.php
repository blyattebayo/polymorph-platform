<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Migration;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Schema\CompiledSchemaTree;

/** Derives the complete migration program from adjacent stable-ID schema trees. */
final class SchemaMigrationCompiler
{
    /** @return list<MigrationOperation> */
    public function compile(CompiledSchemaTree $from, CompiledSchemaTree $to): array
    {
        $oldById = [];
        foreach ($from->fields() as $field) {
            $oldById[$field->id] = $field;
        }
        $newById = [];
        foreach ($to->fields() as $field) {
            $newById[$field->id] = $field;
        }

        $additions = [];
        $moves = [];
        $changes = [];
        $removals = [];
        foreach ($oldById as $fieldId => $old) {
            $new = $newById[$fieldId] ?? null;
            if (! $new instanceof FieldDefinition) {
                if (! $this->hasMissingAncestor($old, $from, $newById)) {
                    $removals[] = $this->operation('remove_field', $old, [
                        'path' => $old->path,
                    ]);
                }

                continue;
            }
            if ($old->name !== $new->name || $old->parentId !== $new->parentId) {
                $moves[] = [count($from->ancestors($old)), $this->operation('move_field', $new, [
                    'from' => $old->path,
                    'to' => $new->path,
                    'old_name' => $old->name,
                    'new_name' => $new->name,
                    'old_parent_field_id' => $old->parentId,
                    'new_parent_field_id' => $new->parentId,
                ])];
            }
            if ($old->typeName() !== $new->typeName()) {
                $changes[] = $this->operation('change_type', $new, [
                    'path' => $new->path,
                    'from' => $old->typeName(),
                    'to' => $new->typeName(),
                ]);
            }
            if ($old->cardinality !== $new->cardinality) {
                $changes[] = $this->operation('change_cardinality', $new, [
                    'path' => $new->path,
                    'from' => $old->cardinality->value,
                    'to' => $new->cardinality->value,
                ]);
            }
            if ($old->constraints !== $new->constraints) {
                $changes[] = $this->operation('update_constraints', $new, ['path' => $new->path]);
            }
            if ($old->projectionVersion !== $new->projectionVersion || $old->metadata !== $new->metadata) {
                $changes[] = $this->operation('rebuild_projections', $new, ['path' => $new->path]);
            }
        }
        usort($moves, static fn (array $left, array $right): int => $left[0] <=> $right[0]);

        foreach ($newById as $fieldId => $new) {
            if (! isset($oldById[$fieldId]) && ! $this->hasNewAncestor($new, $to, $oldById)) {
                $additions[] = [count($to->ancestors($new)), $this->operation('add_field', $new, [
                    'path' => $new->path,
                    'default' => $this->defaultForAddition($new, $to, $oldById),
                ])];
            }
        }
        usort($additions, static fn (array $left, array $right): int => $left[0] <=> $right[0]);

        return [
            ...array_column($additions, 1),
            ...array_column($moves, 1),
            ...$changes,
            ...$removals,
        ];
    }

    /** @param array<string,FieldDefinition> $newById */
    private function hasMissingAncestor(FieldDefinition $field, CompiledSchemaTree $tree, array $newById): bool
    {
        foreach ($tree->ancestors($field) as $ancestor) {
            if (! isset($newById[$ancestor->id])) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,FieldDefinition> $oldById */
    private function hasNewAncestor(FieldDefinition $field, CompiledSchemaTree $tree, array $oldById): bool
    {
        foreach ($tree->ancestors($field) as $ancestor) {
            if (! isset($oldById[$ancestor->id])) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,FieldDefinition> $oldById */
    private function defaultForAddition(FieldDefinition $field, CompiledSchemaTree $tree, array $oldById): mixed
    {
        if ($field->typeName() !== 'json') {
            return null;
        }
        if ($field->cardinality->value === 'many') {
            return [];
        }
        foreach ($tree->fields() as $candidate) {
            if (! isset($oldById[$candidate->id])) {
                continue;
            }
            foreach ($tree->ancestors($candidate) as $ancestor) {
                if ($ancestor->id === $field->id) {
                    return [];
                }
            }
        }

        return null;
    }

    /** @param array<string,mixed> $arguments */
    private function operation(string $kind, FieldDefinition $field, array $arguments): MigrationOperation
    {
        return new MigrationOperation($kind, ['field_id' => $field->id, ...$arguments]);
    }
}
