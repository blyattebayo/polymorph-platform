<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Menu\Providers;

use Polymorph\Platform\Domain\Menu\Access\MenuCapabilityProvider;
use Illuminate\Support\ServiceProvider;

final class MenuServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->tag([MenuCapabilityProvider::class], 'access.capability_providers');
    }
}
