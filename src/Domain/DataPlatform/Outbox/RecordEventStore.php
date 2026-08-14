<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Outbox;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

/** Persists a record audit entry and its outbox messages at the caller's transaction boundary. */
final class RecordEventStore
{
    public function __construct(
        private readonly DatabaseJson $json,
        private readonly OutboxStore $outbox,
    ) {}

    /** @param list<RecordEventMessage> $events */
    public function append(RecordAuditEntry $audit, array $events): void
    {
        DB::table('dp_audit_log')->insert([
            'operation_id' => $audit->operationId,
            'command' => $audit->command,
            'record_id' => $audit->recordId,
            'actor_id' => $audit->actorId,
            'revision' => $audit->revision,
            'changed_field_ids' => $this->json->encode($audit->changedFieldIds),
            'metadata' => $this->json->encode($audit->metadata),
            'created_at' => now(),
        ]);

        foreach ($events as $event) {
            $this->outbox->enqueue(
                operationId: $audit->operationId,
                aggregateType: 'record',
                aggregateId: (string) $audit->recordId,
                eventType: $event->type,
                payload: $event->payload,
                headers: ['operation_id' => $audit->operationId],
            );
        }
    }
}
