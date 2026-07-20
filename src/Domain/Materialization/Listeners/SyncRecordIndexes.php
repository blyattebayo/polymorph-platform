<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Listeners;

use Polymorph\Platform\Domain\Materialization\Services\RecordIndexSyncScheduler;
use Polymorph\Platform\Domain\RecordDefinitions\Events\RecordDefinitionCreated;
use Polymorph\Platform\Domain\SchemaModel\Events\Contracts\SchemaChangeEvent;

/**
 * Держит partial-индексы records в соответствии с is_indexed-полями схемы.
 * Реагирует на изменения полей (add/update/delete — реконсайл по всем определениям
 * схемы) и на создание определения (досоздание индексов для уже индексированных полей).
 */
final class SyncRecordIndexes
{
    public function __construct(
        private readonly RecordIndexSyncScheduler $scheduler,
    ) {}

    public function handleSchemaChange(SchemaChangeEvent $event): void
    {
        $this->scheduler->scheduleSchema($event->schemaId());
    }

    public function handleRecordDefinitionCreated(RecordDefinitionCreated $event): void
    {
        $definition = $event->recordDefinition;

        $this->scheduler->scheduleDefinition((int) $definition->id, (int) $definition->schema_id);
    }
}
