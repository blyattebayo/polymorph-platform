<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\EntryView\Http\Controllers\EntryViewConfigController;
use Polymorph\Platform\Domain\Menu\Access\MenuCapabilities;
use Polymorph\Platform\Domain\Menu\Http\Controllers\MenuConfigController;
use Polymorph\Platform\Domain\SchemaModel\Access\SchemaCapabilities;
use Polymorph\Platform\Domain\TableConfig\Access\TableConfigCapabilities;
use Polymorph\Platform\Domain\TableConfig\Http\Controllers\TableConfigController;

Route::prefix('ui-configs')->name('ui-configs.')->group(function (): void {
    Route::prefix('menu/{key}')
        ->name('menu.')
        ->where(['key' => '[a-z][a-z0-9_]{0,190}'])
        ->group(function (): void {
            Route::get('/', [MenuConfigController::class, 'show'])->name('show');
            Route::middleware(MenuCapabilities::requireManage())->group(function (): void {
                Route::put('/', [MenuConfigController::class, 'update'])->name('update');
                Route::delete('/', [MenuConfigController::class, 'destroy'])->name('destroy');
            });
        });

    Route::prefix('entry-view/{recordDefinition}/{schema}')
        ->name('entry-view.')
        ->whereNumber(['recordDefinition', 'schema'])
        ->group(function (): void {
            Route::get('/', [EntryViewConfigController::class, 'show'])->name('show');
            Route::middleware(SchemaCapabilities::requireManage())->group(function (): void {
                Route::put('/', [EntryViewConfigController::class, 'update'])->name('update');
                Route::delete('/', [EntryViewConfigController::class, 'destroy'])->name('destroy');
            });
        });

    Route::prefix('table/{key}')
        ->name('table.')
        ->where(['key' => '[A-Za-z0-9._-]{1,191}'])
        ->group(function (): void {
            Route::middleware(TableConfigCapabilities::requireManage())->group(function (): void {
                Route::get('/', [TableConfigController::class, 'showGlobal'])->name('show');
                Route::put('/', [TableConfigController::class, 'updateGlobal'])->name('update');
                Route::delete('/', [TableConfigController::class, 'destroyGlobal'])->name('destroy');
            });

            Route::prefix('me')->name('me.')->group(function (): void {
                Route::get('/', [TableConfigController::class, 'showMine'])->name('show');
                Route::put('/', [TableConfigController::class, 'updateMine'])->name('update');
                Route::delete('/', [TableConfigController::class, 'destroyMine'])->name('destroy');
            });
        });
});
