<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionDiscoveryService;
use Polymorph\Platform\Domain\Routing\Console\LintRoutesCommand;
use Polymorph\Platform\Domain\Routing\Http\FallbackController;
use Polymorph\Platform\Domain\Routing\Plugin\PluginRouteMounter;

final class RoutingServiceProvider extends ServiceProvider
{
    /** @var list<string> */
    private const CORE_FILES = [
        'web.php',
        'oauth.php',
        'api.php',
        'api_plugins.php',
        'api_admin.php',
    ];

    public function register(): void
    {
        $this->commands([LintRoutesCommand::class]);
        $this->app->singleton(PluginRouteMounter::class);
    }

    public function boot(): void
    {
        // Laravel replaces this collection with the cached one after providers boot.
        if ($this->app->routesAreCached()) {
            return;
        }

        $this->registerCoreRoutes();

        $mounter = $this->app->make(PluginRouteMounter::class);
        foreach ($this->app->make(ExtensionDiscoveryService::class)->discoverAll() as $extension) {
            if ($extension->backendRouteFile !== null) {
                $mounter->mountFile($extension->id, $extension->backendRouteFile);
            }
        }

        $this->registerFallback();
    }

    private function registerCoreRoutes(): void
    {
        $directory = rtrim((string) config('routing.path', dirname(__DIR__, 3).'/routes'), '/');
        foreach (self::CORE_FILES as $file) {
            require $directory.'/'.$file;
        }
    }

    private function registerFallback(): void
    {
        Route::fallback(FallbackController::class);
        Route::match(['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '{any?}', FallbackController::class)
            ->where('any', '.*')
            ->fallback();
    }
}
