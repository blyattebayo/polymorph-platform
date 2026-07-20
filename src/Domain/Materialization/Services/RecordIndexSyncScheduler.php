<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Services;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Materialization\Jobs\SyncRecordIndexesJob;

final class RecordIndexSyncScheduler
{
    /**
     * @var array<int, true>
     */
    private array $scheduledSchemaIds = [];

    /**
     * @var array<int, true>
     */
    private array $scheduledDefinitionIds = [];

    public function scheduleSchema(int $schemaId): void
    {
        if ($schemaId <= 0 || isset($this->scheduledSchemaIds[$schemaId])) {
            return;
        }

        $this->scheduledSchemaIds[$schemaId] = true;

        DB::afterCommit(function () use ($schemaId): void {
            unset($this->scheduledSchemaIds[$schemaId]);
            SyncRecordIndexesJob::dispatch($schemaId);
        });
    }

    public function scheduleDefinition(int $definitionId, int $schemaId): void
    {
        if ($definitionId <= 0 || isset($this->scheduledDefinitionIds[$definitionId])) {
            return;
        }

        $this->scheduledDefinitionIds[$definitionId] = true;

        DB::afterCommit(function () use ($definitionId, $schemaId): void {
            unset($this->scheduledDefinitionIds[$definitionId]);
            SyncRecordIndexesJob::dispatch(null, $definitionId, $schemaId);
        });
    }
}
