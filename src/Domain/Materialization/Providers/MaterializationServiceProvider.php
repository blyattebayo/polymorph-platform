<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\Materialization\Contracts\RecordDisplayValueProvider;
use Polymorph\Platform\Domain\Materialization\Listeners\ScheduleViewRebuildOnSchemaChange;
use Polymorph\Platform\Domain\Materialization\Listeners\SyncRecordDefinitionDisplayView;
use Polymorph\Platform\Domain\Materialization\Listeners\SyncRecordIndexes;
use Polymorph\Platform\Domain\Materialization\Services\MaterializedRecordDisplayValueProvider;
use Polymorph\Platform\Domain\Materialization\Services\RecordDefinitionViewManager;
use Polymorph\Platform\Domain\Materialization\Services\RecordIndexSyncScheduler;
use Polymorph\Platform\Domain\Materialization\Services\SqlViewCompiler;
use Polymorph\Platform\Domain\Materialization\Services\SqlViewValidator;
use Polymorph\Platform\Domain\RecordDefinitions\Events\RecordDefinitionCreated;
use Polymorph\Platform\Domain\RecordDefinitions\Events\RecordDefinitionDeleted;
use Polymorph\Platform\Domain\RecordDefinitions\Events\RecordDefinitionSchemaChanged;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaChanged;
use Polymorph\Platform\Domain\SchemaModel\ReadModel\SchemaSnapshotService;
use Polymorph\Platform\TemplateEngine\Core\Filters\FilterRegistry;
use Polymorph\Platform\TemplateEngine\Core\Pipeline\TemplateParsePipeline;

class MaterializationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // materialization.php now ships inside the package (dirname(__DIR__, 4) = platform/).
        // PlatformServiceProvider also merges it; mergeConfigFrom is idempotent, and keeping
        // this provider self-sufficient matches its previous behaviour.
        $this->mergeConfigFrom(dirname(__DIR__, 4).'/config/materialization.php', 'materialization');

        $this->app->singleton(SqlViewCompiler::class, function ($app) {
            return new SqlViewCompiler($app->make(FilterRegistry::class));
        });

        $this->app->singleton(RecordDefinitionViewManager::class, function ($app) {
            return new RecordDefinitionViewManager(
                $app->make(SchemaSnapshotService::class),
                $app->make(TemplateParsePipeline::class),
                $app->make(SqlViewValidator::class),
                $app->make(SqlViewCompiler::class),
            );
        });

        $this->app->singleton(RecordDisplayValueProvider::class, MaterializedRecordDisplayValueProvider::class);

        // scoped, не singleton: дедуп-флаги планировщика чистятся в afterCommit, но
        // при rollback колбэк отбрасывается и флаг застревает. На singleton под
        // Octane это навсегда подавляло бы будущие синки схемы; scoped сбрасывает
        // состояние per-request, ограничивая утечку одним запросом (см. C7).
        $this->app->scoped(RecordIndexSyncScheduler::class);
    }

    public function boot(): void
    {
        Event::listen(RecordDefinitionCreated::class, [SyncRecordDefinitionDisplayView::class, 'handleRecordDefinitionCreated']);
        Event::listen(RecordDefinitionSchemaChanged::class, [SyncRecordDefinitionDisplayView::class, 'handleRecordDefinitionSchemaChanged']);
        Event::listen(RecordDefinitionDeleted::class, [SyncRecordDefinitionDisplayView::class, 'handleRecordDefinitionDeleted']);

        // Материализация partial-индексов records из is_indexed-полей схемы
        // (для admin- и plugin-определений одинаково; реконсайл create/drop).
        Event::listen(RecordDefinitionCreated::class, [SyncRecordIndexes::class, 'handleRecordDefinitionCreated']);
        Event::listen(SchemaChanged::class, [SyncRecordIndexes::class, 'handleSchemaChange']);

        // Перестроение display-view определений при изменении схемы/полей
        // Reconcile derived indexes after the canonical schema transaction.
        Event::listen(SchemaChanged::class, ScheduleViewRebuildOnSchemaChange::class);

    }
}
