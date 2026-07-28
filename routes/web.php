<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Extensions\Http\Controllers\ExtensionAssetController;
use Polymorph\Platform\Domain\Routing\Http\HomeController;
use Polymorph\Platform\Http\Controllers\AdminPingController;

/**
 * Системные web-маршруты.
 */
Route::middleware('web')->group(function (): void {
    Route::get('/', [HomeController::class, '__invoke'])->name('home');

    // FE-бандл расширения из его собственной папки (dirname(manifest)/fe/dist).
    // Расширения монтируются в админ-SPA встроенно, бандл отдаёт ядро.
    Route::get('/plugins/{plugin}/fe/{path}', [ExtensionAssetController::class, 'show'])
        ->where('path', '.*')
        ->name('plugins.fe-asset');

    Route::get('/admin/ping', [AdminPingController::class, 'ping']);
});
