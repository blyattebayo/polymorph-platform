<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Migration;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformResourceNotFound;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformStateConflict;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;
use Polymorph\Platform\Domain\DataPlatform\Write\RecordWriteCommand;
use Throwable;

final class SchemaMigrationRunner
{
    public function __construct(
        private readonly SchemaMigrationService $migrations,
        private readonly MaintenanceRecordCommandBus $records,
        private readonly DatabaseJson $json,
    ) {}

    /** @return array{processed:int,failed:int,remaining:int,state:string} */
    public function runBatch(string $planId, int $batchSize = 200, bool $dryRun = false): array
    {
        $plan = DB::table('dp_schema_migration_plans')->where('id', $planId)->first();
        if ($plan === null) {
            throw DataPlatformResourceNotFound::for('migration-plan', $planId);
        }
        $targetState = DB::table('dp_schema_versions')->where('id', $plan->to_schema_version_id)->value('state');
        if (! in_array($targetState, ['backfilling', 'published'], true)) {
            throw DataPlatformStateConflict::because(
                'migration_target_state_not_runnable',
                'Target schema must be backfilling or published.',
                ['plan_id' => $planId, 'target_state' => $targetState],
            );
        }

        $checkpoint = $this->json->decodeMap($plan->checkpoint, 'dp_schema_migration_plans.checkpoint');
        $lastId = (int) ($checkpoint['last_id'] ?? 0);
        $invalid = $this->invalidRecords($plan->invalid_records);
        $retryIds = array_map('intval', array_keys($invalid));
        $rows = DB::table('dp_records')
            ->where('record_definition_id', $plan->record_definition_id)
            ->where('schema_version_id', $plan->from_schema_version_id)
            ->where(function ($query) use ($lastId, $retryIds): void {
                $query->where('id', '>', $lastId);
                if ($retryIds !== []) {
                    $query->orWhereIn('id', $retryIds);
                }
            })
            ->orderByRaw($retryIds === [] ? 'id' : 'CASE WHEN id IN ('.implode(',', array_fill(0, count($retryIds), '?')).') THEN 0 ELSE 1 END, id', $retryIds)
            ->limit(max(1, $batchSize))->get();
        $processed = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $recordId = (int) $row->id;
            $lastId = max($lastId, $recordId);
            try {
                $document = $this->json->decodeMap($row->data, 'dp_records.data');
                $transformed = $this->migrations->transform($planId, $document);
                if (! $dryRun) {
                    $this->records->dispatch(new RecordWriteCommand(
                        recordDefinitionId: (int) $plan->record_definition_id,
                        document: $transformed,
                        actorId: $row->author_id === null ? null : (int) $row->author_id,
                        recordId: (int) $row->id,
                        expectedRevision: (int) $row->revision,
                        idempotencyKey: "schema-migration:{$planId}:{$row->id}:{$row->revision}",
                        schemaVersionId: (string) $plan->to_schema_version_id,
                        replace: true,
                        schemaMigration: true,
                    ));
                }
                $processed++;
                unset($invalid[(string) $recordId]);
            } catch (Throwable $exception) {
                $failed++;
                $previous = $invalid[(string) $recordId] ?? [];
                $invalid[(string) $recordId] = [
                    'record_id' => $recordId,
                    'revision' => (int) $row->revision,
                    'attempts' => (int) ($previous['attempts'] ?? 0) + 1,
                    'error' => $exception::class,
                ];
            }
        }

        $remaining = (int) DB::table('dp_records')
            ->where('record_definition_id', $plan->record_definition_id)
            ->where('schema_version_id', $plan->from_schema_version_id)
            ->count();
        $state = match (true) {
            $remaining === 0 => MigrationPlanState::Completed->value,
            $invalid === [] => MigrationPlanState::Running->value,
            default => MigrationPlanState::RunningWithErrors->value,
        };
        if (! $dryRun) {
            DB::table('dp_schema_migration_plans')->where('id', $planId)->update([
                'state' => $state,
                'checkpoint' => $this->json->encode(['last_id' => $lastId]),
                'invalid_records' => $this->json->encode(array_values(array_slice($invalid, -1000, null, true))),
                'processed_count' => DB::raw('processed_count + '.(int) $processed),
                'failed_count' => count($invalid),
                'updated_at' => now(),
            ]);
        }

        return compact('processed', 'failed', 'remaining', 'state');
    }

    /** @return array<string,array{record_id:int,revision?:int,attempts?:int,error?:string}> */
    private function invalidRecords(mixed $value): array
    {
        $rows = $this->json->decodeList($value, 'dp_schema_migration_plans.invalid_records');
        $result = [];
        foreach ($rows as $row) {
            if (is_array($row) && (int) ($row['record_id'] ?? 0) > 0) {
                $result[(string) (int) $row['record_id']] = $row;
            }
        }

        return $result;
    }
}
