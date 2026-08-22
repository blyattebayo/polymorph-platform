<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\TableConfig\Providers;

use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\TableConfig\Access\TableConfigCapabilities;

final class TableConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->tag([TableConfigCapabilities::class], 'access.capability_providers');
    }
}
