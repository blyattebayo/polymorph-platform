<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields\Handlers;

use Polymorph\Platform\Domain\DataPlatform\Fields\Cardinality;
use Polymorph\Platform\Domain\DataPlatform\Fields\DependencySet;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Fields\ResolvedDependencies;
use Polymorph\Platform\Domain\DataPlatform\Projection\FieldProjectionChanges;
use Polymorph\Platform\Domain\DataPlatform\Query\CompiledPredicate;
use Polymorph\Platform\Domain\DataPlatform\Query\QueryPredicate;
use Polymorph\Platform\Domain\DataPlatform\Validation\DataValidationException;

final class RefFieldTypeHandler extends AbstractFieldTypeHandler
{
    public function type(): string
    {
        return 'ref';
    }

    public function validateSchema(FieldDefinition $field): void
    {
        parent::validateSchema($field);
        $allowed = $field->constraints['allowed_record_definition_ids'] ?? null;
        if (! is_array($allowed) || $allowed === [] || array_filter($allowed, static fn (mixed $id): bool => ! is_int($id) || $id <= 0) !== []) {
            throw DataValidationException::one(
                'ref_allowed_definitions',
                'Ref fields require one or more positive allowed_record_definition_ids.',
                $field->path,
            );
        }

        $policy = (string) ($field->constraints['deletion_policy'] ?? 'restrict');
        if (! in_array($policy, ['restrict', 'nullify', 'preserve_tombstone', 'cascade'], true)) {
            throw DataValidationException::one('deletion_policy', 'Unsupported ref deletion policy.', $field->path);
        }
        if ($policy === 'cascade' && ($field->constraints['allow_cascade'] ?? false) !== true) {
            throw DataValidationException::one('cascade_not_allowed', 'Cascade must be explicitly allowed.', $field->path);
        }
    }

    protected function normalizeOne(mixed $value, FieldDefinition $field, string $occurrence): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        throw DataValidationException::one('ref_id', 'Expected a positive record ID.', $field->path, $occurrence);
    }

    protected function validateOne(mixed $value, FieldDefinition $field, string $occurrence): void
    {
        if (! is_int($value) || $value <= 0) {
            throw DataValidationException::one('ref_id', 'Expected a positive record ID.', $field->path, $occurrence);
        }
    }

    public function collectBatchDependencies(
        mixed $value,
        FieldDefinition $field,
        string $occurrence,
        DependencySet $dependencies,
    ): void {
        foreach ($this->values($value, $field) as $id) {
            $dependencies->addRecord((int) $id);
        }
    }

    public function validateResolvedDependencies(
        mixed $value,
        FieldDefinition $field,
        string $occurrence,
        ResolvedDependencies $dependencies,
    ): void {
        $allowed = array_map('intval', $field->constraints['allowed_record_definition_ids'] ?? []);
        foreach ($this->values($value, $field) as $index => $id) {
            $id = (int) $id;
            $target = $dependencies->records[$id] ?? null;
            $itemOccurrence = $field->cardinality === Cardinality::MANY ? $occurrence.'['.$index.']' : $occurrence;
            if ($target === null) {
                throw DataValidationException::one('ref_missing', 'Referenced record does not exist.', $field->path, $itemOccurrence, ['target_id' => $id]);
            }
            if ($target['deleted_at'] !== null) {
                throw DataValidationException::one('ref_deleted', 'Referenced record is deleted.', $field->path, $itemOccurrence, ['target_id' => $id]);
            }
            if (! in_array((int) $target['record_definition_id'], $allowed, true)) {
                throw DataValidationException::one('ref_wrong_definition', 'Referenced record has a disallowed definition.', $field->path, $itemOccurrence, ['target_id' => $id]);
            }
        }
    }

    public function buildProjectionChanges(
        mixed $value,
        FieldDefinition $field,
        string $occurrence,
    ): FieldProjectionChanges {
        $edges = [];
        foreach ($this->values($value, $field) as $position => $id) {
            $edges[] = [
                'field_id' => $field->id,
                'occurrence' => $occurrence,
                'position' => $position,
                'target_record_id' => (int) $id,
                'deletion_policy' => (string) ($field->constraints['deletion_policy'] ?? 'restrict'),
                'projection_version' => $field->projectionVersion,
            ];
        }

        return new FieldProjectionChanges(refEdges: $edges);
    }

    public function compileQuery(QueryPredicate $predicate): CompiledPredicate
    {
        $this->assertSupportedQueryOperator(
            $predicate,
            'unsupported_ref_operator',
            "Unsupported ref operator '{$predicate->operator}'.",
        );

        return new CompiledPredicate(
            strategy: 'ref_edge',
            cast: null,
        );
    }
}
