<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Listeners;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Materialization\Services\RecordDefinitionViewManager;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaChanged;

/** Rebuilds materialized views after the canonical schema transaction commits. */
final class ScheduleViewRebuildOnSchemaChange
{
    public function __construct(
        private readonly RecordDefinitionViewManager $viewManager,
    ) {}

    public function handle(SchemaChanged $event): void
    {
        DB::afterCommit(fn () => $this->viewManager->rebuildForSchema($event->schemaId));
    }
}
