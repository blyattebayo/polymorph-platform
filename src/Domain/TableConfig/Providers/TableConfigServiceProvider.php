<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\TableConfig\Providers;

use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\TableConfig\Access\TableConfigCapabilityProvider;
use Polymorph\Platform\Domain\TableConfig\Core\Contracts\TableConfigRepository;
use Polymorph\Platform\Domain\TableConfig\Infrastructure\Repositories\EloquentTableConfigRepository;

final class TableConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TableConfigRepository::class, EloquentTableConfigRepository::class);
    }

    public function boot(): void
    {
        $this->app->tag([TableConfigCapabilityProvider::class], 'access.capability_providers');
    }
}
