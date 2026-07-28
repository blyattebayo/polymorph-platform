<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Records\Access\RecordsCapabilities;
use Polymorph\Platform\Domain\Records\Http\Controllers\RecordController;
use Polymorph\Platform\Http\Middleware\RequireCapability;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * CRUD записей.
 *
 * {id} намеренно без ограничения: идентификатор записи задаёт определение, и
 * ограничивать его цифрами на уровне маршрута — значит зашить одну реализацию.
 */
Route::prefix('records')->name('records.')->group(function (): void {
    Route::middleware(RequireCapability::forRoute(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ))
        ->group(function (): void {
            Route::get('/', [RecordController::class, 'index'])->name('index');
            Route::post('/hydrate', [RecordController::class, 'hydrate'])->name('hydrate');
            Route::get('/{id}', [RecordController::class, 'show'])->name('show');
        });

    Route::middleware(RequireCapability::forRoute(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_WRITE))
        ->group(function (): void {
            Route::post('/', [RecordController::class, 'store'])->name('store');
            Route::put('/{id}', [RecordController::class, 'update'])->name('update');
            Route::post('/{id}/restore', [RecordController::class, 'restore'])->name('restore');
        });

    Route::delete('/{id}', [RecordController::class, 'destroy'])
        ->middleware(RequireCapability::forRoute(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_DELETE))
        ->name('destroy');
});
