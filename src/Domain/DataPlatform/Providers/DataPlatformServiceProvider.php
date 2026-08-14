<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\DataPlatform\Access\DataAccessPolicy;
use Polymorph\Platform\Domain\DataPlatform\Access\PlatformDataAccessPolicy;
use Polymorph\Platform\Domain\DataPlatform\Access\RecordsCapabilities;
use Polymorph\Platform\Domain\DataPlatform\Access\SchemaCapabilities;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldTypeRegistry;
use Polymorph\Platform\Domain\DataPlatform\Fields\Handlers\BoolFieldTypeHandler;
use Polymorph\Platform\Domain\DataPlatform\Fields\Handlers\DateTimeFieldTypeHandler;
use Polymorph\Platform\Domain\DataPlatform\Fields\Handlers\FloatFieldTypeHandler;
use Polymorph\Platform\Domain\DataPlatform\Fields\Handlers\IntFieldTypeHandler;
use Polymorph\Platform\Domain\DataPlatform\Fields\Handlers\JsonFieldTypeHandler;
use Polymorph\Platform\Domain\DataPlatform\Fields\Handlers\MediaFieldTypeHandler;
use Polymorph\Platform\Domain\DataPlatform\Fields\Handlers\RefFieldTypeHandler;
use Polymorph\Platform\Domain\DataPlatform\Fields\Handlers\StringFieldTypeHandler;
use Polymorph\Platform\Domain\DataPlatform\Fields\Handlers\TextFieldTypeHandler;
use Polymorph\Platform\Domain\DataPlatform\Fields\SdkFieldTypeHandlerAdapter;
use Polymorph\Platform\Domain\DataPlatform\Outbox\DataPlatformEvent;
use Polymorph\Platform\Domain\DataPlatform\Projection\ProjectionChangeSetBuilder;
use Polymorph\Platform\Domain\DataPlatform\Projection\ProjectionStore;
use Polymorph\Platform\Domain\DataPlatform\Projection\RunDisplayProjectionMaintenance;
use Polymorph\Platform\Domain\DataPlatform\Projection\ScheduleDependentDisplayRebuild;
use Polymorph\Platform\Domain\DataPlatform\Serialization\CanonicalJson;
use Polymorph\Sdk\Data\RegistersFieldTypes;

final class DataPlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FieldTypeRegistry::class, static function ($app): FieldTypeRegistry {
            $canonicalJson = $app->make(CanonicalJson::class);
            $registry = new FieldTypeRegistry([
                new StringFieldTypeHandler($canonicalJson),
                new TextFieldTypeHandler($canonicalJson),
                new IntFieldTypeHandler($canonicalJson),
                new FloatFieldTypeHandler($canonicalJson),
                new BoolFieldTypeHandler($canonicalJson),
                new DateTimeFieldTypeHandler($canonicalJson),
                new JsonFieldTypeHandler($canonicalJson),
                new RefFieldTypeHandler($canonicalJson),
                new MediaFieldTypeHandler($canonicalJson),
            ], static function () use ($app, $canonicalJson): iterable {
                foreach ($app->tagged(RegistersFieldTypes::TAG) as $extension) {
                    yield new SdkFieldTypeHandlerAdapter($extension, $canonicalJson);
                }
            });

            return $registry;
        });
        $this->app->scoped(DataAccessPolicy::class, PlatformDataAccessPolicy::class);
        $this->app->scoped(ProjectionChangeSetBuilder::class);
        $this->app->scoped(ProjectionStore::class);
    }

    public function boot(): void
    {
        $this->app->tag(
            [SchemaCapabilities::class, RecordsCapabilities::class],
            'access.capability_providers',
        );
        Event::listen(DataPlatformEvent::class, ScheduleDependentDisplayRebuild::class);
        Event::listen(DataPlatformEvent::class, RunDisplayProjectionMaintenance::class);
    }
}
