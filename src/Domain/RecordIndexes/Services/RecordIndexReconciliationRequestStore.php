<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordIndexes\Services;

use Illuminate\Support\Facades\DB;
use LogicException;
use Polymorph\Platform\Domain\RecordIndexes\Support\RecordIndexReconciliationRequest;

final class RecordIndexReconciliationRequestStore
{
    public function enqueueSchema(int $schemaId): RecordIndexReconciliationRequest
    {
        return $this->enqueue(RecordIndexReconciliationRequest::TARGET_SCHEMA, $schemaId);
    }

    public function enqueueDefinition(int $definitionId): RecordIndexReconciliationRequest
    {
        return $this->enqueue(RecordIndexReconciliationRequest::TARGET_DEFINITION, $definitionId);
    }

    public function find(int $requestId): ?RecordIndexReconciliationRequest
    {
        if ($requestId <= 0) {
            return null;
        }

        $row = DB::table('record_index_reconciliation_requests')->where('id', $requestId)->first();

        return $row === null ? null : $this->fromRow($row);
    }

    /** @return list<RecordIndexReconciliationRequest> */
    public function pending(): array
    {
        return DB::table('record_index_reconciliation_requests')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): RecordIndexReconciliationRequest => $this->fromRow($row))
            ->all();
    }

    public function deleteIfGeneration(int $requestId, int $generation): bool
    {
        return DB::table('record_index_reconciliation_requests')
            ->where('id', $requestId)
            ->where('generation', $generation)
            ->delete() === 1;
    }

    private function enqueue(string $targetType, int $targetId): RecordIndexReconciliationRequest
    {
        if ($targetId <= 0) {
            throw new LogicException('Record-index reconciliation target id must be positive');
        }
        if (DB::transactionLevel() <= 0) {
            throw new LogicException('Record-index reconciliation requests must be recorded inside the mutation transaction');
        }

        $row = DB::selectOne(
            'INSERT INTO record_index_reconciliation_requests '
            .'(target_type, target_id, generation, created_at, updated_at) '
            .'VALUES (?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) '
            .'ON CONFLICT (target_type, target_id) DO UPDATE SET '
            .'generation = record_index_reconciliation_requests.generation + 1, '
            .'updated_at = CURRENT_TIMESTAMP '
            .'RETURNING id, target_type, target_id, generation',
            [$targetType, $targetId],
        );

        if ($row === null) {
            throw new LogicException('Failed to persist record-index reconciliation request');
        }

        return $this->fromRow($row);
    }

    private function fromRow(object $row): RecordIndexReconciliationRequest
    {
        return new RecordIndexReconciliationRequest(
            id: (int) $row->id,
            targetType: (string) $row->target_type,
            targetId: (int) $row->target_id,
            generation: (int) $row->generation,
        );
    }
}
