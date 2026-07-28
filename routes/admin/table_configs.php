<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\TableConfig\Access\TableConfigCapabilities;
use Polymorph\Platform\Domain\TableConfig\Http\Controllers\TableConfigController;
use Polymorph\Platform\Http\Middleware\RequireCapability;

/**
 * Настройки таблиц.
 *
 * Базовую конфигурацию правит администратор (MANAGE), личные переопределения —
 * сам пользователь: они видны только ему, отдельной капабилити не требуют.
 */
Route::prefix('table-configs/{tableKey}')
    ->name('table-configs.')
    ->where(['tableKey' => '[A-Za-z0-9._-]+'])
    ->group(function (): void {
        Route::middleware(RequireCapability::forRoute(TableConfigCapabilities::MANAGE))
            ->name('base.')
            ->group(function (): void {
                Route::get('/base', [TableConfigController::class, 'showBase'])->name('show');
                Route::put('/base', [TableConfigController::class, 'updateBase'])->name('update');
            });

        Route::prefix('overrides/me')->name('overrides.me.')->group(function (): void {
            Route::get('/', [TableConfigController::class, 'showMyOverride'])->name('show');
            Route::put('/', [TableConfigController::class, 'updateMyOverride'])->name('update');
            Route::delete('/', [TableConfigController::class, 'destroyMyOverride'])->name('destroy');
        });

        Route::get('/resolved', [TableConfigController::class, 'resolved'])->name('resolved');
    });
