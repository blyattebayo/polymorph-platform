<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Providers;

use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\TemplateEngine\Core\Filters\FilterRegistry;

/**
 * Template engine service provider.
 */
class TemplateEngineServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Filter Registry (singleton)
        $this->app->singleton(FilterRegistry::class, function () {
            return new FilterRegistry;
        });
    }
}
