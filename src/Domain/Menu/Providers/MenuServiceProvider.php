<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Menu\Providers;

use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\Menu\Access\MenuCapabilityProvider;

final class MenuServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->tag([MenuCapabilityProvider::class], 'access.capability_providers');
    }
}
