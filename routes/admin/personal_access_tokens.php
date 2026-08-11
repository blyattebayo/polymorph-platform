<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Auth\Http\PersonalAccessToken\Controllers\AdministrativePersonalAccessTokenController;

/**
 * Администрирование персональных токенов.
 *
 * Application use cases enforce the interactive-session and capability rules.
 */
Route::prefix('personal-access-tokens')
    ->name('personal-access-tokens.')
    ->whereUuid('tokenId')
    ->group(function (): void {
        Route::get('/', [AdministrativePersonalAccessTokenController::class, 'index'])
            ->name('index');

        Route::delete('/{tokenId}', [AdministrativePersonalAccessTokenController::class, 'destroy'])
            ->name('destroy');
    });
