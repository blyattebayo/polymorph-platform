<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Media\Access\MediaCapabilities;
use Polymorph\Platform\Domain\Media\Http\Controllers\MediaController;

/**
 * Медиатека: чтение, запись и удаление — три разных полномочия одного ресурса.
 */
Route::prefix('media')->name('media.')->group(function (): void {
    Route::middleware(MediaCapabilities::requireRead())
        ->group(function (): void {
            Route::get('/config', [MediaController::class, 'config'])->name('config');
            Route::get('/', [MediaController::class, 'index'])->name('index');
            // Последним среди GET: иначе {media} перехватит /media/config.
            Route::get('/{media}', [MediaController::class, 'show'])->name('show');
        });

    Route::middleware(MediaCapabilities::requireWrite())
        ->group(function (): void {
            Route::post('/', [MediaController::class, 'store'])->name('store');
            Route::post('/bulk', [MediaController::class, 'bulkStore'])->name('bulkStore');
            Route::put('/{media}', [MediaController::class, 'update'])->name('update');
            Route::post('/bulk/restore', [MediaController::class, 'bulkRestore'])->name('bulkRestore');
        });

    Route::middleware(MediaCapabilities::requireDelete())
        ->group(function (): void {
            Route::delete('/bulk', [MediaController::class, 'bulkDestroy'])->name('bulkDestroy');
            Route::delete('/bulk/force', [MediaController::class, 'bulkForceDestroy'])->name('bulkForceDestroy');
            Route::delete('/trash/empty', [MediaController::class, 'emptyTrash'])->name('emptyTrash');
        });
});
