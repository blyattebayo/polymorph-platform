<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Control;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformInvariantViolation;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformResourceNotFound;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformStateConflict;
use Polymorph\Platform\Domain\DataPlatform\Migration\MigrationPlanValidator;
use Polymorph\Platform\Domain\DataPlatform\Projection\ProjectionDefinitionService;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaCatalog;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaState;

/** Enforces and persists schema-version state transitions. */
final class SchemaLifecycleService
{
    public function __construct(
        private readonly SchemaCatalog $schemas,
        private readonly SchemaValidator $validator,
        private readonly MigrationPlanValidator $migrationPlans,
        private readonly ProjectionDefinitionService $projections,
    ) {}

    public function transition(string $schemaVersionId, SchemaState $next): void
    {
        DB::transaction(function () use ($schemaVersionId, $next): void {
            $version = DB::table('dp_schema_versions')->where('id', $schemaVersionId)->lockForUpdate()->first();
            if ($version === null) {
                throw DataPlatformResourceNotFound::for('schema-version', $schemaVersionId);
            }

            $current = SchemaState::tryFrom((string) $version->state);
            if (! $current instanceof SchemaState) {
                throw DataPlatformInvariantViolation::because(
                    'unknown_stored_schema_state',
                    "Schema version {$schemaVersionId} has an unknown stored state.",
                    ['schema_version_id' => $schemaVersionId, 'state' => $version->state],
                );
            }
            if (! $current->canTransitionTo($next)) {
                throw DataPlatformStateConflict::because(
                    'invalid_schema_transition',
                    "Invalid schema transition {$current->value} -> {$next->value}.",
                    ['schema_version_id' => $schemaVersionId, 'from' => $current->value, 'to' => $next->value],
                );
            }

            $update = ['state' => $next->value, 'updated_at' => now()];
            if ($next === SchemaState::Validating) {
                $update['checksum'] = $this->validator->validate($this->schemas->fields($schemaVersionId));
                $update['validated_at'] = now();
                $this->projections->synchronize($schemaVersionId);
            }
            if ($next === SchemaState::Published) {
                $this->publish($version, $schemaVersionId, $update);
            }
            if ($next === SchemaState::Archived) {
                $update['archived_at'] = now();
            }

            DB::table('dp_schema_versions')->where('id', $schemaVersionId)->update($update);
        });
    }

    /** @param array<string, mixed> $update */
    private function publish(object $version, string $schemaVersionId, array &$update): void
    {
        if (! is_string($version->checksum) || $version->checksum === '') {
            throw DataPlatformStateConflict::because(
                'schema_not_validated',
                'A schema must be validated before publication.',
                ['schema_version_id' => $schemaVersionId],
            );
        }

        $definitionId = (int) $version->record_definition_id;
        $oldVersionId = DB::table('dp_record_definitions')
            ->where('id', $definitionId)
            ->lockForUpdate()
            ->value('current_schema_version_id');
        if (is_string($oldVersionId) && $oldVersionId !== $schemaVersionId) {
            $this->migrationPlans->assertPublicationReady($oldVersionId, $schemaVersionId);
            DB::table('dp_schema_versions')->where('id', $oldVersionId)->update([
                'state' => SchemaState::Archived->value,
                'archived_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('dp_record_definitions')->where('id', $definitionId)->update([
            'current_schema_version_id' => $schemaVersionId,
            'updated_at' => now(),
        ]);
        $update['published_at'] = now();
    }
}
