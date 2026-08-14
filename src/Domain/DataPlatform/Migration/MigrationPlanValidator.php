<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Migration;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformStateConflict;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaCatalog;
use Polymorph\Platform\Domain\DataPlatform\Serialization\CanonicalJson;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

/** Proves that a plan is the exact server-compiled diff for adjacent schema trees. */
final class MigrationPlanValidator
{
    public function __construct(
        private readonly SchemaCatalog $schemas,
        private readonly SchemaMigrationCompiler $compiler,
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
        if ($plan->classification === MigrationClassification::ForbiddenWithoutExplicitMigration) {
            throw DataPlatformStateConflict::because(
                'forbidden_schema_change',
                'A forbidden schema change cannot be published.',
                ['from_schema_version_id' => $fromVersionId, 'to_schema_version_id' => $toVersionId],
            );
        }
        $expected = $this->compiler->compile(
            $this->schemas->tree($fromVersionId),
            $this->schemas->tree($toVersionId),
        );
        $expectedPayload = array_map(static fn (MigrationOperation $operation): array => $operation->toArray(), $expected);
        $actualPayload = array_map(static fn (MigrationOperation $operation): array => $operation->toArray(), $plan->operations);
        if ($this->canonicalJson->hash($expectedPayload) !== $this->canonicalJson->hash($actualPayload)) {
            throw DataPlatformStateConflict::because(
                'migration_plan_does_not_match_schema_diff',
                'Migration plan does not match the adjacent server-compiled schema diff.',
                ['expected' => $expectedPayload, 'actual' => $actualPayload],
            );
        }
        if ($plan->classification === MigrationClassification::MetadataOnly
            && $this->storageSignature($fromVersionId) !== $this->storageSignature($toVersionId)) {
            throw DataPlatformStateConflict::because(
                'metadata_plan_changes_storage',
                'A metadata-only plan cannot publish document or projection schema changes.',
                ['from_schema_version_id' => $fromVersionId, 'to_schema_version_id' => $toVersionId],
            );
        }
        if ($plan->classification === MigrationClassification::MetadataOnly) {
            return;
        }

        $remaining = (int) DB::table('dp_records')
            ->where('schema_version_id', $fromVersionId)
            ->whereNull('deleted_at')
            ->count();
        if ($plan->state !== MigrationPlanState::Completed || $plan->failedCount !== 0 || $remaining !== 0) {
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

    private function storageSignature(string $schemaVersionId): string
    {
        return $this->canonicalJson->hash(array_map(static fn (FieldDefinition $field): array => [
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
        ], $this->schemas->fields($schemaVersionId)));
    }
}
