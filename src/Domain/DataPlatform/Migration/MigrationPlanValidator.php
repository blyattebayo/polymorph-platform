<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Migration;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Control\SchemaCatalog;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformStateConflict;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Serialization\CanonicalJson;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

/** Proves that an adjacent migration plan exactly acknowledges its schema diff. */
final class MigrationPlanValidator
{
    public function __construct(
        private readonly SchemaCatalog $schemas,
        private readonly DatabaseJson $json,
        private readonly CanonicalJson $canonicalJson,
    ) {}

    public function assertPublicationReady(string $fromVersionId, string $toVersionId): void
    {
        $row = DB::table('dp_schema_migration_plans')
            ->where('from_schema_version_id', $fromVersionId)
            ->where('to_schema_version_id', $toVersionId)
            ->lockForUpdate()
            ->first();
        if ($row === null) {
            throw DataPlatformStateConflict::because(
                'missing_contiguous_migration_plan',
                'Publishing a successor schema requires exactly one contiguous migration plan.',
                ['from_schema_version_id' => $fromVersionId, 'to_schema_version_id' => $toVersionId],
            );
        }

        $plan = MigrationPlan::fromRow($row, $this->json);
        if ($plan->classification === 'forbidden-without-explicit-migration') {
            throw DataPlatformStateConflict::because(
                'forbidden_schema_change',
                'A forbidden schema change cannot be published without replacing its migration plan.',
                ['from_schema_version_id' => $fromVersionId, 'to_schema_version_id' => $toVersionId],
            );
        }

        $this->assertOperationsCoverDiff($fromVersionId, $toVersionId, $plan->operations);
        if ($plan->classification === 'metadata-only'
            && $this->storageSignature($fromVersionId) !== $this->storageSignature($toVersionId)) {
            throw DataPlatformStateConflict::because(
                'metadata_plan_changes_storage',
                'A metadata-only plan cannot publish document or projection schema changes.',
                ['from_schema_version_id' => $fromVersionId, 'to_schema_version_id' => $toVersionId],
            );
        }
        if ($plan->classification === 'metadata-only') {
            return;
        }

        $remaining = (int) DB::table('dp_records')->where('schema_version_id', $fromVersionId)->count();
        if ($plan->state !== MigrationPlanState::Completed->value || $plan->failedCount !== 0 || $remaining !== 0) {
            throw DataPlatformStateConflict::because(
                'schema_migration_incomplete',
                "Schema migration must complete without errors before publication; {$remaining} records remain on the previous version.",
                [
                    'from_schema_version_id' => $fromVersionId,
                    'to_schema_version_id' => $toVersionId,
                    'remaining_records' => $remaining,
                    'failed_records' => $plan->failedCount,
                ],
            );
        }
    }

    /** @param list<MigrationOperation> $operations */
    private function assertOperationsCoverDiff(string $fromVersionId, string $toVersionId, array $operations): void
    {
        $from = collect($this->schemas->fields($fromVersionId))->keyBy('id');
        $to = collect($this->schemas->fields($toVersionId))->keyBy('id');
        foreach ($operations as $operation) {
            if (! in_array($operation->kind, ['split', 'merge'], true)
                && ! $this->operationMatchesDiff($operation, $from, $to)) {
                throw DataPlatformStateConflict::because(
                    'migration_plan_has_extra_operation',
                    "Migration operation '{$operation->kind}' does not match the adjacent schema diff.",
                    ['operation' => $operation->toArray()],
                );
            }
        }
        $has = static function (string $kind, callable $matches) use ($operations): bool {
            foreach ($operations as $operation) {
                if ($operation->kind === $kind && $matches($operation)) {
                    return true;
                }
            }

            return false;
        };

        foreach ($from as $fieldId => $old) {
            $new = $to->get($fieldId);
            if (! $new instanceof FieldDefinition) {
                $this->requireOperation(
                    $has('remove_field', static fn (MigrationOperation $op): bool => $op->argument('path') === $old->path),
                    'migration_plan_missing_remove_field',
                    "Migration plan does not remove deleted field '{$old->path}'.",
                    ['field_id' => $old->id, 'path' => $old->path, 'required_operation' => 'remove_field'],
                );

                continue;
            }

            $moved = $has('rename_field', static fn (MigrationOperation $op): bool => $op->argument('from') === $old->path && $op->argument('to') === $new->path)
                || $has('move_field', static fn (MigrationOperation $op): bool => $op->argument('from') === $old->path && $op->argument('to') === $new->path);
            $this->requireWhen($old->path !== $new->path, $moved, 'migration_plan_missing_move_field',
                "Migration plan does not move renamed field '{$old->path}' to '{$new->path}'.",
                ['field_id' => $new->id, 'from' => $old->path, 'to' => $new->path, 'required_operation' => 'rename_field|move_field']);
            $this->requireWhen($old->type !== $new->type,
                $has('change_type', static fn (MigrationOperation $op): bool => $op->argument('path') === $new->path),
                'migration_plan_missing_change_type', "Migration plan does not convert field '{$new->path}'.",
                ['field_id' => $new->id, 'path' => $new->path, 'from' => $old->type, 'to' => $new->type, 'required_operation' => 'change_type']);
            $this->requireWhen($old->cardinality !== $new->cardinality,
                $has('change_cardinality', static fn (MigrationOperation $op): bool => $op->argument('path') === $new->path && $op->argument('to') === $new->cardinality->value),
                'migration_plan_missing_change_cardinality', "Migration plan does not change cardinality for '{$new->path}'.",
                ['field_id' => $new->id, 'path' => $new->path, 'from' => $old->cardinality->value, 'to' => $new->cardinality->value, 'required_operation' => 'change_cardinality']);
            $this->requireWhen($old->constraints !== $new->constraints,
                $has('update_constraints', static fn (MigrationOperation $op): bool => $op->argument('path') === $new->path),
                'migration_plan_missing_update_constraints', "Migration plan does not acknowledge constraints changed for '{$new->path}'.",
                ['field_id' => $new->id, 'path' => $new->path, 'required_operation' => 'update_constraints']);
            $this->requireWhen($old->projectionVersion !== $new->projectionVersion,
                $has('rebuild_projections', static fn (MigrationOperation $op): bool => $op->argument('field_id') === $new->id || $op->argument('path') === $new->path),
                'migration_plan_missing_rebuild_projections', "Migration plan does not rebuild projections for '{$new->path}'.",
                ['field_id' => $new->id, 'path' => $new->path, 'required_operation' => 'rebuild_projections']);
        }

        foreach ($to as $fieldId => $new) {
            $this->requireWhen(! $from->has($fieldId),
                $has('add_field', static fn (MigrationOperation $op): bool => $op->argument('path') === $new->path),
                'migration_plan_missing_add_field', "Migration plan does not add new field '{$new->path}'.",
                ['field_id' => $new->id, 'path' => $new->path, 'required_operation' => 'add_field']);
        }
    }

    private function storageSignature(string $schemaVersionId): string
    {
        return $this->canonicalJson->hash(array_map(static fn (FieldDefinition $field): array => [
            'id' => $field->id,
            'path' => $field->path,
            'type' => $field->type,
            'cardinality' => $field->cardinality->value,
            'system' => $field->system,
            'projection_version' => $field->projectionVersion,
            'constraints' => $field->constraints,
            'parent_id' => $field->parentId,
            'position' => $field->position,
        ], $this->schemas->fields($schemaVersionId)));
    }

    private function operationMatchesDiff(MigrationOperation $operation, Collection $from, Collection $to): bool
    {
        foreach ($from as $fieldId => $old) {
            $new = $to->get($fieldId);
            if (! $new instanceof FieldDefinition) {
                if ($operation->kind === 'remove_field' && $operation->argument('path') === $old->path) {
                    return true;
                }

                continue;
            }

            if (in_array($operation->kind, ['rename_field', 'move_field'], true)
                && $old->path !== $new->path
                && $operation->argument('from') === $old->path
                && $operation->argument('to') === $new->path) {
                return true;
            }
            if ($operation->kind === 'change_type' && $old->type !== $new->type
                && $operation->argument('path') === $new->path) {
                return true;
            }
            if ($operation->kind === 'change_cardinality' && $old->cardinality !== $new->cardinality
                && $operation->argument('path') === $new->path
                && $operation->argument('to') === $new->cardinality->value) {
                return true;
            }
            if ($operation->kind === 'update_constraints' && $old->constraints !== $new->constraints
                && $operation->argument('path') === $new->path) {
                return true;
            }
            if ($operation->kind === 'rebuild_projections' && $old->projectionVersion !== $new->projectionVersion
                && ($operation->argument('field_id') === $new->id || $operation->argument('path') === $new->path)) {
                return true;
            }
        }

        if ($operation->kind === 'add_field') {
            foreach ($to as $fieldId => $new) {
                if (! $from->has($fieldId) && $operation->argument('path') === $new->path) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<string, mixed> $metadata */
    private function requireWhen(bool $required, bool $covered, string $reason, string $message, array $metadata): void
    {
        if ($required) {
            $this->requireOperation($covered, $reason, $message, $metadata);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function requireOperation(bool $covered, string $reason, string $message, array $metadata): void
    {
        if (! $covered) {
            throw DataPlatformStateConflict::because($reason, $message, $metadata);
        }
    }
}
