<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordIndexes\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\RecordIndexes\Listeners\ScheduleRecordIndexReconciliation;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaChanged;

final class RecordIndexesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(SchemaChanged::class, ScheduleRecordIndexReconciliation::class);
    }
}
