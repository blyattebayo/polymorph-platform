<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Polymorph\Platform\Domain\DataPlatform\Control\SchemaCatalog;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformResourceNotFound;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

final class SchemaMigrationService
{
    public const CLASSIFICATIONS = [
        'metadata-only', 'additive', 'projection-rebuild', 'lazy-document-migration',
        'breaking-migration', 'forbidden-without-explicit-migration',
    ];

    /** @var array<string,MigrationPlan> */
    private array $planCache = [];

    /** @var array<string,array<string,FieldDefinition>> */
    private array $targetFieldCache = [];

    public function __construct(
        private readonly SchemaCatalog $schemas,
        private readonly MigrationOperationExecutor $operations,
        private readonly DatabaseJson $json,
    ) {}

    /** @param list<MigrationOperation> $operations */
    public function createPlan(string $fromVersionId, string $toVersionId, string $classification, array $operations): string
    {
        if (! in_array($classification, self::CLASSIFICATIONS, true)) {
            throw DataPlatformBadRequest::because(
                'unsupported_migration_classification',
                "Unsupported migration classification '{$classification}'.",
                ['classification' => $classification],
            );
        }
        $from = DB::table('dp_schema_versions')->where('id', $fromVersionId)->first();
        $to = DB::table('dp_schema_versions')->where('id', $toVersionId)->first();
        if ($from === null || $to === null || (int) $from->record_definition_id !== (int) $to->record_definition_id) {
            throw DataPlatformBadRequest::because(
                'migration_version_definition_mismatch',
                'Migration versions must exist on the same definition.',
                ['from_schema_version_id' => $fromVersionId, 'to_schema_version_id' => $toVersionId],
            );
        }
        if ((string) $to->previous_version_id !== $fromVersionId) {
            throw DataPlatformBadRequest::because(
                'non_contiguous_migration_plan',
                'Migration plans must form a contiguous version chain.',
                ['from_schema_version_id' => $fromVersionId, 'to_schema_version_id' => $toVersionId],
            );
        }

        $id = (string) Str::ulid();
        DB::table('dp_schema_migration_plans')->insert([
            'id' => $id,
            'record_definition_id' => (int) $from->record_definition_id,
            'from_schema_version_id' => $fromVersionId,
            'to_schema_version_id' => $toVersionId,
            'classification' => $classification,
            'state' => MigrationPlanState::Draft->value,
            'operations' => $this->json->encode(array_map(
                static fn (MigrationOperation $operation): array => $operation->toArray(),
                $operations,
            )),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /** @param array<string,mixed> $document @return array<string,mixed> */
    public function transform(string $planId, array $document): array
    {
        if (! isset($this->planCache[$planId])) {
            $row = DB::table('dp_schema_migration_plans')->where('id', $planId)->first();
            if ($row === null) {
                throw DataPlatformResourceNotFound::for('migration-plan', $planId);
            }
            $this->planCache[$planId] = MigrationPlan::fromRow($row, $this->json);
        }
        $plan = $this->planCache[$planId];
        if ($plan->operations === []) {
            return $document;
        }
        $targetVersionId = $plan->toVersionId;
        $targetFields = $this->targetFieldCache[$targetVersionId]
            ??= $this->schemas->fieldsByPath($targetVersionId);

        return $this->operations->execute(
            $document,
            $plan->operations,
            $targetFields,
        );
    }
}
