<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Outbox;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;
use Throwable;

/** At-least-once dispatcher; reservation commits before any listener is invoked. */
final class OutboxDispatcher
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly DatabaseJson $json,
    ) {}

    public function dispatchBatch(?int $limit = null): int
    {
        $workerId = (string) Str::uuid();
        $limit ??= max(1, (int) config('data_platform.outbox.batch_size'));
        $lockTimeout = max(1, (int) config('data_platform.outbox.lock_timeout_seconds'));

        $rows = DB::transaction(function () use ($workerId, $limit, $lockTimeout): array {
            $rows = DB::table('dp_outbox')
                ->whereNull('delivered_at')
                ->whereNull('dead_lettered_at')
                ->where('available_at', '<=', now())
                ->where(function ($query) use ($lockTimeout): void {
                    $query->whereNull('locked_at')->orWhere('locked_at', '<', now()->subSeconds($lockTimeout));
                })
                ->orderBy('available_at')
                ->orderBy('id')
                ->limit($limit)
                ->lock('FOR UPDATE SKIP LOCKED')
                ->get();

            $ids = $rows->pluck('id')->all();
            if ($ids !== []) {
                DB::table('dp_outbox')->whereIn('id', $ids)->update([
                    'locked_at' => now(),
                    'locked_by' => $workerId,
                    'attempts' => DB::raw('attempts + 1'),
                    'updated_at' => now(),
                ]);
            }

            return $rows->all();
        });

        $delivered = 0;
        foreach ($rows as $row) {
            try {
                $this->events->dispatch(new DataPlatformEvent(
                    id: (string) $row->id,
                    operationId: (string) $row->operation_id,
                    type: (string) $row->event_type,
                    aggregateType: (string) $row->aggregate_type,
                    aggregateId: (string) $row->aggregate_id,
                    payload: $this->json->decodeMap($row->payload, 'dp_outbox.payload'),
                    headers: $this->json->decodeMap($row->headers, 'dp_outbox.headers'),
                ));
                DB::table('dp_outbox')->where('id', $row->id)->where('locked_by', $workerId)->update([
                    'delivered_at' => now(),
                    'locked_at' => null,
                    'locked_by' => null,
                    'last_error' => null,
                    'updated_at' => now(),
                ]);
                $delivered++;
            } catch (Throwable $exception) {
                $attempt = (int) $row->attempts + 1;
                $deadLettered = $attempt >= max(1, (int) config('data_platform.outbox.max_attempts'));
                $backoff = min(
                    max(1, (int) config('data_platform.outbox.max_backoff_seconds')),
                    max(1, (int) config('data_platform.outbox.backoff_base_seconds'))
                        ** min(max(1, (int) config('data_platform.outbox.max_backoff_exponent')), (int) $row->attempts + 1),
                );
                DB::table('dp_outbox')->where('id', $row->id)->where('locked_by', $workerId)->update([
                    'available_at' => $deadLettered ? $row->available_at : now()->addSeconds($backoff),
                    'locked_at' => null,
                    'locked_by' => null,
                    'dead_lettered_at' => $deadLettered ? now() : null,
                    'last_error' => mb_substr(
                        $exception->getMessage(),
                        0,
                        max(1, (int) config('data_platform.outbox.max_error_length')),
                    ),
                    'updated_at' => now(),
                ]);
            }
        }

        return $delivered;
    }
}
