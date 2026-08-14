<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Migration;

use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldTypeRegistry;
use Polymorph\Platform\Domain\DataPlatform\Schema\CompiledSchemaTree;

/** Executes only server-compiled, stable-field migration operations. */
final class MigrationOperationExecutor
{
    public const OPERATIONS = [
        'move_field', 'add_field', 'remove_field', 'change_type',
        'change_cardinality', 'update_constraints', 'rebuild_projections',
    ];

    public function __construct(private readonly FieldTypeRegistry $types) {}

    /** @param list<MigrationOperation> $operations @return array<string,mixed> */
    public function execute(
        array $document,
        array $operations,
        CompiledSchemaTree $from,
        CompiledSchemaTree $to,
    ): array {
        foreach ($operations as $operation) {
            $document = match ($operation->kind) {
                'move_field' => $this->move($document, $operation, $from, $to),
                'add_field' => $this->add($document, $operation, $to),
                'remove_field' => $this->remove($document, $operation, $from),
                'change_type' => $this->changeType($document, $operation, $to),
                'change_cardinality' => $this->changeCardinality($document, $operation, $to),
                'update_constraints', 'rebuild_projections' => $document,
            };
        }

        return $document;
    }

    /** @return array<string,mixed> */
    private function move(
        array $document,
        MigrationOperation $operation,
        CompiledSchemaTree $from,
        CompiledSchemaTree $to,
    ): array {
        $fieldId = $this->fieldId($operation);
        $old = $from->field($fieldId);
        $new = $to->field($fieldId);
        $oldParent = $old->parentId === null ? null : $from->field($old->parentId);
        $newParent = $new->parentId === null ? null : $to->field($new->parentId);
        if ($old->parentId === $new->parentId) {
            return $from->mapObjectOccurrences($document, $oldParent, static function (array $object) use ($old, $new): array {
                if (! array_key_exists($old->name, $object)) {
                    return $object;
                }
                $value = $object[$old->name];
                unset($object[$old->name]);
                $object[$new->name] = $value;

                return $object;
            });
        }

        $values = $from->values($document, $old);
        if ($values === []) {
            return $document;
        }
        if (count($values) !== 1 || $old->multiValued || $new->multiValued) {
            throw DataPlatformBadRequest::because(
                'ambiguous_repeated_field_move',
                "Moving repeated field '{$old->path}' requires an occurrence-scoped transformer.",
                ['field_id' => $fieldId, 'from' => $old->path, 'to' => $new->path],
            );
        }
        $value = $values[0]['value'];
        $document = $from->mapObjectOccurrences($document, $oldParent, static function (array $object) use ($old): array {
            unset($object[$old->name]);

            return $object;
        });
        $document = $to->mapObjectOccurrences($document, $newParent, static function (array $object) use ($new, $value): array {
            $object[$new->name] = $value;

            return $object;
        });
        if ($to->values($document, $new) === []) {
            throw DataPlatformBadRequest::because(
                'migration_target_parent_missing',
                "Cannot move '{$old->path}' because target parent '{$newParent?->path}' is absent.",
                ['field_id' => $fieldId, 'to' => $new->path],
            );
        }

        return $document;
    }

    /** @return array<string,mixed> */
    private function add(array $document, MigrationOperation $operation, CompiledSchemaTree $to): array
    {
        $field = $to->field($this->fieldId($operation));
        $parent = $field->parentId === null ? null : $to->field($field->parentId);

        return $to->mapObjectOccurrences($document, $parent, static function (array $object) use ($field, $operation): array {
            if (! array_key_exists($field->name, $object)) {
                $object[$field->name] = $operation->argument('default');
            }

            return $object;
        });
    }

    /** @return array<string,mixed> */
    private function remove(array $document, MigrationOperation $operation, CompiledSchemaTree $from): array
    {
        $field = $from->field($this->fieldId($operation));
        $parent = $field->parentId === null ? null : $from->field($field->parentId);

        return $from->mapObjectOccurrences($document, $parent, static function (array $object) use ($field): array {
            unset($object[$field->name]);

            return $object;
        });
    }

    /** @return array<string,mixed> */
    private function changeType(array $document, MigrationOperation $operation, CompiledSchemaTree $to): array
    {
        $field = $to->field($this->fieldId($operation));
        $handler = $this->types->get($field->type);

        return $to->map($document, $field, static function (mixed $value, string $occurrence) use ($handler, $field): mixed {
            $normalized = $handler->normalize($value, $field, $occurrence);
            $handler->validateValue($normalized, $field, $occurrence);

            return $normalized;
        });
    }

    /** @return array<string,mixed> */
    private function changeCardinality(array $document, MigrationOperation $operation, CompiledSchemaTree $to): array
    {
        $field = $to->field($this->fieldId($operation));
        $target = (string) $operation->argument('to');

        return $to->map($document, $field, static function (mixed $value) use ($target, $field): mixed {
            if ($target === 'many') {
                return $value === null ? [] : (is_array($value) && array_is_list($value) ? $value : [$value]);
            }
            if ($target === 'one') {
                if (! is_array($value) || ! array_is_list($value)) {
                    return $value;
                }
                if (count($value) > 1) {
                    throw DataPlatformBadRequest::because(
                        'cardinality_collapse_requires_transformer',
                        "Cannot collapse '{$field->path}' to cardinality one without an occurrence-scoped transformer.",
                        ['field_id' => $field->id, 'path' => $field->path],
                    );
                }

                return $value[0] ?? null;
            }

            throw DataPlatformBadRequest::because('invalid_target_cardinality', "Invalid target cardinality '{$target}'.");
        });
    }

    private function fieldId(MigrationOperation $operation): string
    {
        $fieldId = trim((string) $operation->argument('field_id'));
        if ($fieldId === '') {
            throw DataPlatformBadRequest::because(
                'migration_field_id_missing',
                "Migration operation '{$operation->kind}' requires a stable field ID.",
            );
        }

        return $fieldId;
    }
}
