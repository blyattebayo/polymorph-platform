<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Extensions\Access\ExtensionsCapabilities;
use Polymorph\Platform\Domain\Extensions\Http\Controllers\ExtensionAdminController;
use Polymorph\Platform\Http\Middleware\RequireCapability;

/**
 * Жизненный цикл расширений.
 */
Route::prefix('plugins')
    ->name('plugins.')
    ->middleware(RequireCapability::forRoute(ExtensionsCapabilities::MANAGE))
    ->group(function (): void {
        Route::get('/', [ExtensionAdminController::class, 'index'])->name('index');
        Route::post('/enable', [ExtensionAdminController::class, 'enable'])->name('enable');
        Route::post('/disable', [ExtensionAdminController::class, 'disable'])->name('disable');
        Route::post('/update', [ExtensionAdminController::class, 'update'])->name('update');
    });
