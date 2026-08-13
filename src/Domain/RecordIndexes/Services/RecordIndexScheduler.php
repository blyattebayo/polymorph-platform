<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordIndexes\Services;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\RecordIndexes\Jobs\ReconcileRecordIndexesJob;
use Polymorph\Platform\Domain\RecordIndexes\Support\RecordIndexReconciliationRequest;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Throwable;

final class RecordIndexScheduler
{
    public function __construct(
        private readonly RecordIndexReconciliationRequestStore $requests,
        private readonly Dispatcher $dispatcher,
        private readonly AppLogger $logger,
    ) {}

    public function scheduleSchema(int $schemaId): void
    {
        if ($schemaId <= 0) {
            return;
        }

        $this->dispatchAfterCommit($this->requests->enqueueSchema($schemaId));
    }

    public function scheduleDefinition(int $definitionId): void
    {
        if ($definitionId <= 0) {
            return;
        }

        $this->dispatchAfterCommit($this->requests->enqueueDefinition($definitionId));
    }

    private function dispatchAfterCommit(RecordIndexReconciliationRequest $request): void
    {
        DB::afterCommit(function () use ($request): void {
            try {
                $this->dispatcher->dispatch(new ReconcileRecordIndexesJob($request->id, $request->generation));
            } catch (Throwable $exception) {
                // The durable request was committed with the mutation. Keep the successful
                // product response truthful; doctor --repair can recover a failed dispatch.
                $this->logger->error('records.index_reconciliation.dispatch_failed', [
                    'event' => 'records.index_reconciliation.dispatch_failed',
                    'request_id' => $request->id,
                    'generation' => $request->generation,
                    'target_type' => $request->targetType,
                    'target_id' => $request->targetId,
                    'exception' => $exception,
                ]);
            }
        });
    }
}
