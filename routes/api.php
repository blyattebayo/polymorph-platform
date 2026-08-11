<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Auth\Http\Controllers\CurrentUserController;
use Polymorph\Platform\Domain\Auth\Http\Controllers\LoginController;
use Polymorph\Platform\Domain\Auth\Http\Controllers\LogoutController;
use Polymorph\Platform\Domain\Auth\Http\Controllers\MeAuthSessionController;
use Polymorph\Platform\Domain\Auth\Http\PersonalAccessToken\Controllers\OwnPersonalAccessTokenController;
use Polymorph\Platform\Domain\Media\Http\Controllers\MediaPreviewController;
use Polymorph\Platform\Domain\Menu\Http\Controllers\MenuController;
use Polymorph\Platform\Http\Middleware\ResolveSessionCredential;
use Polymorph\Platform\Support\Validation\Http\ValidationRulesController;

/**
 * Публичное API ядра.
 */
Route::middleware('api')->prefix('api/v1')->group(function (): void {
    Route::get('/validation-rules', [ValidationRulesController::class, '__invoke'])->name('api.v1.validation-rules');

    Route::prefix('auth')->name('api.auth.')->middleware('no-cache-auth')->group(function (): void {
        Route::post('/login', [LoginController::class, '__invoke'])
            ->middleware('throttle:auth-login')
            ->name('login');

        Route::middleware('auth:session')->group(function (): void {
            Route::post('/logout', [LogoutController::class, '__invoke'])->name('logout');
            Route::get('/current', [CurrentUserController::class, '__invoke'])->name('current');
        });
    });

    Route::prefix('me')
        ->name('api.me.')
        ->middleware(['auth:session', 'no-cache-auth'])
        ->where([
            'sessionId' => '[0-9a-fA-F-]{36}',
            'tokenId' => '[0-9a-fA-F-]{36}',
        ])
        ->group(function (): void {
            Route::get('/sessions', [MeAuthSessionController::class, 'index'])->name('sessions.index');
            Route::delete('/sessions/{sessionId}', [MeAuthSessionController::class, 'destroy'])
                ->whereUuid('sessionId')
                ->name('sessions.destroy');

            Route::name('personal-access-tokens.')->group(function (): void {
                Route::get('/personal-access-tokens', [OwnPersonalAccessTokenController::class, 'index'])->name('index');
                Route::post('/personal-access-tokens', [OwnPersonalAccessTokenController::class, 'store'])
                    ->middleware('throttle:pat-create')
                    ->name('store');
                Route::delete('/personal-access-tokens/{tokenId}', [OwnPersonalAccessTokenController::class, 'destroy'])
                    ->whereUuid('tokenId')
                    ->name('destroy');
            });
        });

    // Меню навигации по ключу: дефолты и ACL-фильтрацию делает FE.
    Route::get('/menu/{key}', [MenuController::class, 'show'])
        ->where('key', '[a-z][a-z0-9_]*')
        ->middleware('auth:session')
        ->name('api.v1.menu.show');

    // Публичная выдача медиа: доступ решает контроллер, поэтому auth-middleware
    // здесь нет — анонимный запрос не должен получать 401 на публичный файл.
    // Актора контроллер спрашивает у AuthenticationContext, который разбирает
    // запрос по требованию; отдельный «опциональный» middleware для этого не нужен.
    Route::get('/media/{id}', [MediaPreviewController::class, 'show'])
        ->middleware(ResolveSessionCredential::ALIAS)
        ->name('api.v1.media.show');
});
