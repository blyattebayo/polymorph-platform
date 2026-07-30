<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Roles\Access\RolesCapabilities;
use Polymorph\Platform\Domain\Roles\Http\Controllers\RoleController;
use Polymorph\Platform\Domain\Users\Access\UsersCapabilities;
use Polymorph\Platform\Domain\Users\Http\Controllers\UserController;

/**
 * Пользователи и роли: видеть — одна капабилити, менять состав — другая.
 */
Route::prefix('roles')->name('roles.')->whereNumber('roleId')->group(function (): void {
    Route::get('/', [RoleController::class, 'index'])
        ->middleware(RolesCapabilities::requireRead())
        ->name('index');

    Route::middleware(RolesCapabilities::requireManage())->group(function (): void {
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::delete('/bulk', [RoleController::class, 'bulkDestroy'])->name('bulkDestroy');
        Route::delete('/{roleId}', [RoleController::class, 'destroy'])->name('destroy');
    });
});

Route::prefix('users')
    ->name('users.')
    ->whereNumber('userId')
    ->middleware(UsersCapabilities::requireRead())
    ->group(function (): void {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/{userId}', [UserController::class, 'show'])->name('show');

        Route::middleware(UsersCapabilities::requireManage())->group(function (): void {
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::put('/{userId}', [UserController::class, 'update'])->name('update');
            Route::post('/{userId}/password', [UserController::class, 'setPassword'])->name('password');
        });
    });
