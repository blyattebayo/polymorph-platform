<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\SchemaModel\Access\SchemaCapabilities;
use Polymorph\Platform\Domain\SchemaModel\Http\Controllers\SchemaController;
use Polymorph\Platform\Http\Middleware\RequireCapability;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * Схемы данных: чтение по ресурсной капабилити, правка — по MANAGE.
 */
Route::prefix('schema-models')->name('schema-models.')->whereNumber('schema')->group(function (): void {
    Route::middleware(RequireCapability::forRoute(SchemaCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ))
        ->group(function (): void {
            Route::get('/', [SchemaController::class, 'index'])->name('index');
            Route::get('/{schema}', [SchemaController::class, 'show'])->name('show');
            Route::get('/{schema}/tree', [SchemaController::class, 'tree'])->name('tree');
            Route::get('/{schema}/usage', [SchemaController::class, 'usage'])->name('usage');
        });

    Route::middleware(RequireCapability::forRoute(SchemaCapabilities::MANAGE))->group(function (): void {
        Route::post('/', [SchemaController::class, 'store'])->name('store');
        Route::delete('/bulk', [SchemaController::class, 'bulkDestroy'])->name('bulkDestroy');
        Route::put('/{schema}', [SchemaController::class, 'update'])->name('update');
        Route::delete('/{schema}', [SchemaController::class, 'destroy'])->name('destroy');
    });
});
