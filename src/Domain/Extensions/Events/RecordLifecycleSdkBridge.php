<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Events;

use Illuminate\Support\Facades\Event;
use Polymorph\Platform\Domain\DataPlatform\StorageKey;
use Polymorph\Platform\Domain\Records\Events\RecordDeleted;
use Polymorph\Sdk\Events\RecordDeleted as SdkRecordDeleted;

/**
 * Translates the platform's internal record-lifecycle events into the declared Extension SDK
 * contract (ADR 0005 Фаза 4). Extensions listen to `Polymorph\Sdk\Events\*` instead of the
 * platform's internal event/model classes; this bridge is the single place that couples the
 * two, and it only re-emits for extension-owned records (schema code parses as `ext__…`).
 */
final class RecordLifecycleSdkBridge
{
    public function __construct(
        private readonly RecordDefinitionSchemaCode $schemaCodes,
    ) {}

    public function handle(RecordDeleted $event): void
    {
        $schemaCode = $this->schemaCodes->forDefinition($event->before->recordDefinitionId);
        if ($schemaCode === null) {
            return;
        }

        $parsed = StorageKey::parse($schemaCode);
        if ($parsed === null) {
            // Platform-owned record (not `ext__…`): no extension contract to emit.
            return;
        }

        Event::dispatch(new SdkRecordDeleted(
            extensionId: $parsed['extensionId'],
            entity: $parsed['entity'],
            schemaCode: $schemaCode,
            recordId: $event->before->id->value,
            data: $event->before->dataJson,
        ));
    }
}
