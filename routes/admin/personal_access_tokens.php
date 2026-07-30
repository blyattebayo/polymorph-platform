<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Auth\Http\Controllers\AdminPersonalAccessTokenController;
use Polymorph\Platform\Domain\Users\Access\UsersCapabilities;
use Polymorph\Platform\Http\Middleware\EnsureSessionCredential;

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
            ->middleware(UsersCapabilities::requireRead())
            ->name('index');

        Route::delete('/{tokenId}', [AdminPersonalAccessTokenController::class, 'destroy'])
            ->middleware([UsersCapabilities::requireManage(), EnsureSessionCredential::ALIAS])
            ->name('destroy');
    });

Route::prefix('users/{userId}/personal-access-tokens')
    ->name('users.personal-access-tokens.')
    ->whereNumber('userId')
    ->middleware(UsersCapabilities::requireRead())
    ->group(function (): void {
        Route::get('/', [AdminPersonalAccessTokenController::class, 'indexForUser'])->name('index');

        Route::post('/', [AdminPersonalAccessTokenController::class, 'storeForUser'])
            ->middleware([
                'throttle:pat-create',
                UsersCapabilities::requireManage(),
                EnsureSessionCredential::ALIAS,
            ])
            ->name('store');
    });
