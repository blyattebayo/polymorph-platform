<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Menu\Access\MenuCapabilities;
use Polymorph\Platform\Domain\Menu\Http\Controllers\MenuAdminController;
use Polymorph\Platform\Http\Middleware\RequireCapability;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * Редактирование меню навигации. Чтение живёт в публичном API (api.v1.menu.show).
 */
Route::prefix('menu/{key}')
    ->name('menu.')
    ->where(['key' => '[a-z][a-z0-9_]*'])
    ->middleware(RequireCapability::forRoute(MenuCapabilities::RESOURCE, CapabilityCatalog::ACTION_MANAGE))
    ->group(function (): void {
        Route::get('/', [MenuAdminController::class, 'show'])->name('show');
        Route::put('/', [MenuAdminController::class, 'update'])->name('update');
        Route::delete('/', [MenuAdminController::class, 'destroy'])->name('destroy');
    });
