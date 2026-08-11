<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Extensions\Http\Controllers\ExtensionFrontendController;

/**
 * Метаданные расширений для админ-SPA.
 *
 * Это маршруты ЯДРА о расширениях, а не маршруты самих расширений: последние
 * монтируются под api/v1/ext/{plugin} из файлов плагинов (PluginRouteMounter).
 */
Route::middleware(['api', 'auth:session'])->prefix('api/v1/plugins')->group(function (): void {
    Route::get('/frontend-manifest', [ExtensionFrontendController::class, 'index'])
        ->name('api.v1.plugins.frontend-manifest');
});
