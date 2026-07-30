<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\SchemaModel\Access\SchemaCapabilities;
use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\FieldRepository;
use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\FieldWriteService;
use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\SchemaFieldPathReadModel;
use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\SchemaRepository;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\FieldRefConstraint;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Core\SystemFields\SystemFieldSeeder;
use Polymorph\Platform\Domain\SchemaModel\Events\FieldAdded;
use Polymorph\Platform\Domain\SchemaModel\Events\FieldDeleted;
use Polymorph\Platform\Domain\SchemaModel\Events\FieldUpdated;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaCreated;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaDeleted;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaUpdated;
use Polymorph\Platform\Domain\SchemaModel\Infrastructure\Repositories\EloquentFieldRepository;
use Polymorph\Platform\Domain\SchemaModel\Infrastructure\Repositories\EloquentSchemaFieldPathReadModel;
use Polymorph\Platform\Domain\SchemaModel\Infrastructure\Repositories\EloquentSchemaRepository;
use Polymorph\Platform\Domain\SchemaModel\Listeners\InvalidateSnapshotCacheOnSchemaChange;
use Polymorph\Platform\Domain\SchemaModel\Observers\FieldObserver;
use Polymorph\Platform\Domain\SchemaModel\Observers\FieldRefConstraintObserver;
use Polymorph\Platform\Domain\SchemaModel\Observers\SchemaObserver;
use Polymorph\Platform\Domain\SchemaModel\ReadModel\Contracts\SchemaSnapshotServiceInterface;
use Polymorph\Platform\Domain\SchemaModel\ReadModel\SchemaSnapshotService;
use Polymorph\Platform\Domain\SchemaModel\Services\ConstraintManager;
use Polymorph\Platform\Domain\SchemaModel\Services\FieldMutationService;
use Polymorph\Platform\Domain\SchemaModel\Services\FieldPathCalculator;
use Polymorph\Platform\Domain\SchemaModel\Services\SchemaViewRebuildScheduler;

/**
 * Service Provider для Schema Domain.
 *
 * Регистрирует все bindings, repositories и services.
 */
class SchemaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Repository bindings
        $this->app->singleton(SchemaRepository::class, EloquentSchemaRepository::class);
        $this->app->singleton(FieldRepository::class, EloquentFieldRepository::class);
        $this->app->singleton(SchemaFieldPathReadModel::class, EloquentSchemaFieldPathReadModel::class);
        $this->app->singleton(SystemFieldSeeder::class);

        // Service bindings
        $this->app->singleton(FieldPathCalculator::class);
        $this->app->singleton(FieldMutationService::class);
        $this->app->singleton(FieldWriteService::class, static fn ($app): FieldWriteService => $app->make(FieldMutationService::class));
        $this->app->singleton(ConstraintManager::class);
        $this->app->singleton(SchemaViewRebuildScheduler::class);
        $this->app->singleton(SchemaSnapshotService::class);
        $this->app->singleton(SchemaSnapshotServiceInterface::class, static fn ($app): SchemaSnapshotServiceInterface => $app->make(SchemaSnapshotService::class));
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register observers
        SchemaModel::observe(SchemaObserver::class);
        Field::observe(FieldObserver::class);
        FieldRefConstraint::observe(FieldRefConstraintObserver::class);

        // Инвалидация кэша снапшота схемы при изменении схемы/полей.
        foreach ([FieldAdded::class, FieldUpdated::class, FieldDeleted::class, SchemaCreated::class, SchemaUpdated::class, SchemaDeleted::class] as $event) {
            Event::listen($event, InvalidateSnapshotCacheOnSchemaChange::class);
        }

        $this->app->tag([SchemaCapabilities::class], 'access.capability_providers');
    }
}
