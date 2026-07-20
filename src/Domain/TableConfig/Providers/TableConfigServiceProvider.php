<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\TableConfig\Providers;

use Polymorph\Platform\Domain\TableConfig\Access\TableConfigCapabilityProvider;
use Polymorph\Platform\Domain\TableConfig\Core\Contracts\TableConfigRepository;
use Polymorph\Platform\Domain\TableConfig\Infrastructure\Repositories\EloquentTableConfigRepository;
use Illuminate\Support\ServiceProvider;

final class TableConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TableConfigRepository::class, EloquentTableConfigRepository::class);
    }

    public function boot(): void
    {
        $this->app->tag([TableConfigCapabilityProvider::class], 'access.capability_providers');
    }
}
