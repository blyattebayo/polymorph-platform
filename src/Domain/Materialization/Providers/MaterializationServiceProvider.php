<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Providers;

use Polymorph\Platform\Domain\Materialization\Contracts\RecordDisplayValueProvider;
use Polymorph\Platform\Domain\Materialization\Listeners\ScheduleViewRebuildOnSchemaChange;
use Polymorph\Platform\Domain\Materialization\Listeners\SyncRecordDefinitionDisplayView;
use Polymorph\Platform\Domain\Materialization\Listeners\SyncRecordIndexes;
use Polymorph\Platform\Domain\Materialization\Services\MaterializedRecordDisplayValueProvider;
use Polymorph\Platform\Domain\Materialization\Services\RecordIndexSyncScheduler;
use Polymorph\Platform\Domain\SchemaModel\Events\FieldAdded;
use Polymorph\Platform\Domain\SchemaModel\Events\FieldDeleted;
use Polymorph\Platform\Domain\SchemaModel\Events\FieldUpdated;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaCreated;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaDeleted;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaUpdated;
use Polymorph\Platform\Domain\SchemaModel\ReadModel\Contracts\SchemaSnapshotServiceInterface;
use Polymorph\Platform\Domain\RecordDefinitions\Events\RecordDefinitionCreated;
use Polymorph\Platform\Domain\RecordDefinitions\Events\RecordDefinitionDeleted;
use Polymorph\Platform\Domain\RecordDefinitions\Events\RecordDefinitionSchemaChanged;
use Polymorph\Platform\TemplateEngine\Core\Pipeline\TemplateParsePipeline;
use Polymorph\Platform\Domain\Materialization\Services\RecordDefinitionViewManager;
use Polymorph\Platform\Domain\Materialization\Services\SqlViewCompiler;
use Polymorph\Platform\Domain\Materialization\Services\SqlViewValidator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class MaterializationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // materialization.php now ships inside the package (dirname(__DIR__, 4) = platform/).
        // PlatformServiceProvider also merges it; mergeConfigFrom is idempotent, and keeping
        // this provider self-sufficient matches its previous behaviour.
        $this->mergeConfigFrom(dirname(__DIR__, 4) . '/config/materialization.php', 'materialization');

        $this->app->singleton(SqlViewCompiler::class, function ($app) {
            return new SqlViewCompiler($app->make(\Polymorph\Platform\TemplateEngine\Core\Filters\FilterRegistry::class));
        });

        $this->app->singleton(RecordDefinitionViewManager::class, function ($app) {
            return new RecordDefinitionViewManager(
                $app->make(SchemaSnapshotServiceInterface::class),
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
        Event::listen(FieldAdded::class, [SyncRecordIndexes::class, 'handleSchemaChange']);
        Event::listen(FieldUpdated::class, [SyncRecordIndexes::class, 'handleSchemaChange']);
        Event::listen(FieldDeleted::class, [SyncRecordIndexes::class, 'handleSchemaChange']);

        // Перестроение display-view определений при изменении схемы/полей
        // (перенесено из FieldObserver/SchemaObserver).
        foreach ([FieldAdded::class, FieldUpdated::class, FieldDeleted::class, SchemaCreated::class, SchemaUpdated::class, SchemaDeleted::class] as $event) {
            Event::listen($event, ScheduleViewRebuildOnSchemaChange::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Polymorph\Platform\Domain\Materialization\Console\Commands\RebuildRecordDefinitionDisplayViewsCommand::class,
                \Polymorph\Platform\Domain\Materialization\Console\Commands\RecordIndexesDoctorCommand::class,
            ]);
        }
    }
}

