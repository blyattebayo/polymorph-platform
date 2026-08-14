<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Events;

use Illuminate\Support\Facades\Event;
use Polymorph\Platform\Domain\DataPlatform\Outbox\DataPlatformEvent;
use Polymorph\Platform\Domain\DataPlatform\Outbox\RecordEventType;
use Polymorph\Platform\Domain\DataPlatform\SdkBridge\ExtensionStorageKey;
use Polymorph\Sdk\Events\RecordDeleted as SdkRecordDeleted;

/** Translates internal deletion events into the public SDK event for extension-owned records. */
final class RecordLifecycleSdkBridge
{
    public function __construct(private readonly RecordDefinitionSchemaCode $schemaCodes) {}

    public function handle(DataPlatformEvent $event): void
    {
        if ($event->type !== RecordEventType::Deleted->value) {
            return;
        }
        $definitionId = (int) ($event->payload['record_definition_id'] ?? 0);
        $recordId = (int) ($event->payload['record_id'] ?? 0);
        if ($definitionId <= 0 || $recordId <= 0) {
            return;
        }
        $schemaCode = $this->schemaCodes->forDefinition($definitionId);
        if ($schemaCode === null) {
            return;
        }

        $parsed = ExtensionStorageKey::parse($schemaCode);
        if ($parsed === null) {
            return;
        }
        $data = $event->payload['data'] ?? [];

        Event::dispatch(new SdkRecordDeleted(
            extensionId: $parsed['extensionId'],
            entity: $parsed['entity'],
            schemaCode: $schemaCode,
            recordId: $recordId,
            data: is_array($data) ? $data : [],
        ));
    }
}
