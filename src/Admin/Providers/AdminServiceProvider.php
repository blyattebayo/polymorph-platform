<?php

declare(strict_types=1);

namespace Polymorph\Platform\Admin\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Admin\AdminBundle;
use Polymorph\Platform\Admin\Http\AdminAssetController;
use Polymorph\Platform\Admin\Http\AdminShellController;

/**
 * Wires the admin SPA into routing at the host-configured mount path
 * (config('admin.path'), default /admin):
 *   - {path}/assets/{asset}  -> static bundle files (registered FIRST)
 *   - {path}/{any?}          -> SPA shell for deep links / reloads
 *
 * Both are public (the SPA authenticates against the API via the opaque session cookie; the
 * shell itself needs no auth). Route::fallback() (RoutingServiceProvider) is always
 * matched last by Laravel, so the catch-all here never shadows genuine 404s.
 */
final class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $prefix = AdminBundle::routePrefix();

        Route::get($prefix.'/assets/{asset}', [AdminAssetController::class, 'show'])
            ->where('asset', '.*')
            ->name('admin.asset');

        Route::get($prefix.'/{any?}', AdminShellController::class)
            ->where('any', '.*')
            ->name('admin.shell');
    }
}
