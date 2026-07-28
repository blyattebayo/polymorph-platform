<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Auth\Http\Controllers\AuthController;
use Polymorph\Platform\Domain\Auth\Http\Controllers\EmailVerificationController;
use Polymorph\Platform\Domain\Auth\Http\Controllers\MeAuthSessionController;
use Polymorph\Platform\Domain\Auth\Http\Controllers\MePersonalAccessTokenController;
use Polymorph\Platform\Domain\Auth\Http\Controllers\PasswordResetController;
use Polymorph\Platform\Domain\Media\Http\Controllers\MediaPreviewController;
use Polymorph\Platform\Domain\Menu\Http\Controllers\MenuController;
use Polymorph\Platform\Http\Middleware\EnsureSessionCredential;
use Polymorph\Platform\Http\Middleware\OptionalApiAuth;
use Polymorph\Platform\Support\Validation\Http\ValidationRulesController;

/**
 * Публичное API ядра.
 */
Route::middleware('api')->prefix('api/v1')->group(function (): void {
    Route::get('/validation-rules', [ValidationRulesController::class, '__invoke'])->name('api.v1.validation-rules');

    // Ссылка из письма: подписанный GET, открывается браузером, отвечает
    // редиректом в SPA. Стоит вне группы auth по двум причинам: это
    // единственный маршрут аутентификации без no-cache-auth, и его имя
    // задано Laravel — MustVerifyEmail строит URL по 'verification.verify'.
    Route::get('/auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('throttle:30,1')
        ->name('verification.verify');

    Route::prefix('auth')->name('api.auth.')->middleware('no-cache-auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:auth-register')
            ->name('register');

        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:auth-login')
            ->name('login');

        Route::post('/refresh', [AuthController::class, 'refresh'])
            ->middleware('throttle:auth-refresh')
            ->name('refresh');

        Route::prefix('password')
            ->name('password.')
            ->middleware('throttle:auth-password-reset')
            ->group(function (): void {
                Route::post('/forgot', [PasswordResetController::class, 'forgot'])->name('forgot');
                Route::post('/reset', [PasswordResetController::class, 'reset'])->name('reset');
            });

        Route::middleware('auth:api')->group(function (): void {
            Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
                ->middleware('throttle:auth-verify-resend')
                ->name('email.resend');

            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('/current', [AuthController::class, 'current'])->name('current');
        });
    });

    // Управление собственными сессиями и токенами. EnsureSessionCredential
    // требует, чтобы запрос шёл под сессионной кукой, а не под PAT: иначе
    // долгоживущим токеном можно было бы отозвать сессии владельца.
    Route::prefix('me')
        ->name('api.me.')
        ->middleware(['auth:api', EnsureSessionCredential::ALIAS, 'no-cache-auth'])
        ->whereNumber(['sessionId', 'tokenId'])
        ->group(function (): void {
            Route::get('/sessions', [MeAuthSessionController::class, 'index'])->name('sessions.index');
            Route::delete('/sessions/{sessionId}', [MeAuthSessionController::class, 'destroy'])->name('sessions.destroy');

            Route::name('personal-access-tokens.')->group(function (): void {
                Route::get('/personal-access-tokens', [MePersonalAccessTokenController::class, 'index'])->name('index');
                Route::post('/personal-access-tokens', [MePersonalAccessTokenController::class, 'store'])
                    ->middleware('throttle:pat-create')
                    ->name('store');
                Route::delete('/personal-access-tokens/{tokenId}', [MePersonalAccessTokenController::class, 'destroy'])->name('destroy');
            });
        });

    // Меню навигации по ключу: дефолты и ACL-фильтрацию делает FE.
    Route::get('/menu/{key}', [MenuController::class, 'show'])
        ->where('key', '[a-z][a-z0-9_]*')
        ->middleware('auth:api')
        ->name('api.v1.menu.show');

    // Публичная выдача медиа: доступ решает контроллер, поэтому аутентификация
    // опциональная — анонимный запрос не должен получать 401 на публичный файл.
    Route::get('/media/{id}', [MediaPreviewController::class, 'show'])
        ->middleware(OptionalApiAuth::ALIAS)
        ->name('api.v1.media.show');
});
