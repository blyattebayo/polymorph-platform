<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Outbox;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

/** Sole append boundary for durable Data Platform outbox messages. */
final class OutboxStore
{
    public function __construct(private readonly DatabaseJson $json) {}

    /** @param array<string, mixed> $payload @param array<string, mixed> $headers */
    public function enqueue(
        string $operationId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload,
        array $headers = [],
    ): void {
        DB::table('dp_outbox')->insert([
            'id' => (string) Str::ulid(),
            'operation_id' => $operationId,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'payload' => $this->json->encode($payload),
            'headers' => $this->json->encode($headers),
            'available_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
