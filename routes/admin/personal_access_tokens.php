<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Auth\Http\Controllers\AdminPersonalAccessTokenController;
use Polymorph\Platform\Domain\Users\Access\UsersCapabilities;
use Polymorph\Platform\Http\Middleware\EnsureSessionCredential;
use Polymorph\Platform\Http\Middleware\RequireCapability;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * Администрирование персональных токенов.
 *
 * Выдача и отзыв требуют сессионной куки (EnsureSessionCredential): иначе
 * долгоживущим токеном можно было бы выписать себе новый и продлить доступ
 * без повторного входа.
 */
Route::prefix('personal-access-tokens')
    ->name('personal-access-tokens.')
    ->whereNumber('tokenId')
    ->group(function (): void {
        Route::get('/', [AdminPersonalAccessTokenController::class, 'indexAll'])
            ->middleware(RequireCapability::forRoute(UsersCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ))
            ->name('index');

        Route::delete('/{tokenId}', [AdminPersonalAccessTokenController::class, 'destroy'])
            ->middleware([RequireCapability::forRoute(UsersCapabilities::RESOURCE, CapabilityCatalog::ACTION_MANAGE), EnsureSessionCredential::ALIAS])
            ->name('destroy');
    });

Route::prefix('users/{userId}/personal-access-tokens')
    ->name('users.personal-access-tokens.')
    ->whereNumber('userId')
    ->middleware(RequireCapability::forRoute(UsersCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ))
    ->group(function (): void {
        Route::get('/', [AdminPersonalAccessTokenController::class, 'indexForUser'])->name('index');

        Route::post('/', [AdminPersonalAccessTokenController::class, 'storeForUser'])
            ->middleware([
                'throttle:pat-create',
                RequireCapability::forRoute(UsersCapabilities::RESOURCE, CapabilityCatalog::ACTION_MANAGE),
                EnsureSessionCredential::ALIAS,
            ])
            ->name('store');
    });
