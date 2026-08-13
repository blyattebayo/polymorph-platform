<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Providers;

use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\SchemaModel\Access\SchemaCapabilities;
use Polymorph\Platform\Domain\SchemaModel\ReadModel\SchemaSnapshotService;
use Polymorph\Platform\Domain\SchemaModel\Services\FieldAccessService;
use Polymorph\Platform\Domain\SchemaModel\Services\SchemaFieldVisibility;

final class SchemaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(FieldAccessService::class);
        $this->app->scoped(SchemaFieldVisibility::class);
        $this->app->scoped(SchemaSnapshotService::class);
    }

    public function boot(): void
    {
        $this->app->tag([SchemaCapabilities::class], 'access.capability_providers');
    }
}
