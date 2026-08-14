<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

use Polymorph\Platform\Domain\DataPlatform\Outbox\DataPlatformEvent;

/** Processes one bounded display-projection maintenance batch per outbox event. */
final class RunDisplayProjectionMaintenance
{
    public function __construct(
        private readonly ProjectionRebuilder $rebuilder,
        private readonly ProjectionRebuildScheduler $scheduler,
    ) {}

    public function handle(DataPlatformEvent $event): void
    {
        if ($event->type === ProjectionRebuildScheduler::DEFINITION_EVENT) {
            $this->rebuildDefinition($event);
        }
        if ($event->type === ProjectionRebuildScheduler::DEPENDENTS_EVENT) {
            $this->rebuildDependents($event);
        }
    }

    private function rebuildDefinition(DataPlatformEvent $event): void
    {
        $definitionId = (int) ($event->payload['record_definition_id'] ?? 0);
        if ($definitionId <= 0) {
            return;
        }
        $batch = $this->rebuilder->rebuildDefinitionBatch(
            $definitionId,
            (int) ($event->payload['after_record_id'] ?? 0),
            $this->batchSize(),
        );
        $this->cascadeChangedRecords($batch);
        if ($batch->mayHaveMore) {
            $this->scheduler->scheduleDefinition($definitionId, $batch->lastRecordId);
        }
    }

    private function rebuildDependents(DataPlatformEvent $event): void
    {
        $targetRecordId = (int) ($event->payload['target_record_id'] ?? 0);
        if ($targetRecordId <= 0) {
            return;
        }
        $batch = $this->rebuilder->rebuildDependentsBatch(
            $targetRecordId,
            (int) ($event->payload['after_source_record_id'] ?? 0),
            $this->batchSize(),
        );
        $this->cascadeChangedRecords($batch);
        if ($batch->mayHaveMore) {
            $this->scheduler->scheduleDependents($targetRecordId, $batch->lastRecordId);
        }
    }

    private function cascadeChangedRecords(ProjectionRebuildBatchResult $batch): void
    {
        foreach ($batch->changedRecordIds as $recordId) {
            $this->scheduler->scheduleDependents($recordId);
        }
    }

    private function batchSize(): int
    {
        return max(1, (int) config('data_platform.display.rebuild_batch_size'));
    }
}
