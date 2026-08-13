<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordIndexes\Listeners;

use Polymorph\Platform\Domain\RecordIndexes\Services\RecordIndexScheduler;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaChanged;

final class ScheduleRecordIndexReconciliation
{
    public function __construct(
        private readonly RecordIndexScheduler $scheduler,
    ) {}

    public function handle(SchemaChanged $event): void
    {
        $this->scheduler->scheduleSchema($event->schemaId);
    }
}
