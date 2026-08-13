<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordIndexes\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use LogicException;
use Polymorph\Platform\Domain\RecordIndexes\Services\RecordIndexReconciliationProcessor;
use Polymorph\Platform\Domain\RecordIndexes\Services\RecordIndexReconciliationRequestStore;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Throwable;

final class ReconcileRecordIndexesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        private readonly int $requestId,
        private readonly int $generation,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function handle(
        RecordIndexReconciliationRequestStore $requests,
        RecordIndexReconciliationProcessor $processor,
    ): void {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Performance index reconciliation must run outside a transaction');
        }

        $request = $requests->find($this->requestId);
        if ($request === null || $request->generation !== $this->generation) {
            return;
        }

        $processor->process($request);
    }

    public function failed(?Throwable $exception): void
    {
        app(AppLogger::class)->error('records.index_reconciliation.failed', [
            'event' => 'records.index_reconciliation.failed',
            'request_id' => $this->requestId,
            'generation' => $this->generation,
            'exception' => $exception,
        ]);
    }
}
