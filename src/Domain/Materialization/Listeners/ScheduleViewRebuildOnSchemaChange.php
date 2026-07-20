<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Listeners;

use Polymorph\Platform\Domain\SchemaModel\Events\Contracts\SchemaChangeEvent;
use Polymorph\Platform\Domain\SchemaModel\Services\SchemaViewRebuildScheduler;

/**
 * Планирует перестроение display-view определений при изменении схемы/полей.
 *
 * Перенесено из FieldObserver/SchemaObserver: материализация сама подписана на
 * SchemaChangeEvent, а write-домен SchemaModel больше не дёргает планировщик
 * напрямую. Планировщик дедуплицирует по schemaId и откладывает на afterCommit.
 */
final class ScheduleViewRebuildOnSchemaChange
{
    public function __construct(
        private readonly SchemaViewRebuildScheduler $rebuildScheduler,
    ) {}

    public function handle(SchemaChangeEvent $event): void
    {
        $this->rebuildScheduler->schedule($event->schemaId());
    }
}
