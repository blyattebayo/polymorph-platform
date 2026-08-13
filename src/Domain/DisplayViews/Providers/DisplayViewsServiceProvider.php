<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DisplayViews\Providers;

use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\DisplayViews\Services\RecordDefinitionDisplayViewSynchronizer;
use Polymorph\Platform\Domain\DisplayViews\Services\SqlDisplayViewCompiler;
use Polymorph\Platform\TemplateEngine\Core\Filters\FilterRegistry;

final class DisplayViewsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            SqlDisplayViewCompiler::class,
            static fn ($app): SqlDisplayViewCompiler => new SqlDisplayViewCompiler($app->make(FilterRegistry::class)),
        );

        // The synchronizer consumes the request-scoped schema snapshot cache and must share its lifetime.
        $this->app->scoped(RecordDefinitionDisplayViewSynchronizer::class);
    }
}
