<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

use Illuminate\Support\Str;
use Polymorph\Platform\Domain\DataPlatform\Outbox\OutboxStore;

/** Persists bounded projection-maintenance work in the existing outbox. */
final class ProjectionRebuildScheduler
{
    public const DEFINITION_EVENT = 'data.display.definition.rebuild.requested';

    public const DEPENDENTS_EVENT = 'data.display.dependents.rebuild.requested';

    public function __construct(private readonly OutboxStore $outbox) {}

    public function scheduleDefinition(int $definitionId, int $afterRecordId = 0): void
    {
        $this->schedule(self::DEFINITION_EVENT, 'record-definition', $definitionId, [
            'record_definition_id' => $definitionId,
            'after_record_id' => $afterRecordId,
        ]);
    }

    public function scheduleDependents(int $targetRecordId, int $afterSourceRecordId = 0): void
    {
        $this->schedule(self::DEPENDENTS_EVENT, 'record', $targetRecordId, [
            'target_record_id' => $targetRecordId,
            'after_source_record_id' => $afterSourceRecordId,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function schedule(string $eventType, string $aggregateType, int $aggregateId, array $payload): void
    {
        $operationId = (string) Str::uuid();
        $this->outbox->enqueue(
            operationId: $operationId,
            aggregateType: $aggregateType,
            aggregateId: (string) $aggregateId,
            eventType: $eventType,
            payload: $payload,
            headers: ['operation_id' => $operationId, 'maintenance' => true],
        );
    }
}
