<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Auth\Http\OAuth\OAuthAuthorizationController;
use Polymorph\Platform\Domain\Auth\Http\OAuth\OAuthClientRegistrationController;
use Polymorph\Platform\Domain\Auth\Http\OAuth\OAuthMetadataController;
use Polymorph\Platform\Domain\Auth\Http\OAuth\OAuthRevocationController;
use Polymorph\Platform\Domain\Auth\Http\OAuth\OAuthTokenController;
use Polymorph\Platform\Http\Middleware\PreventClickjacking;

Route::get('/.well-known/oauth-authorization-server', [OAuthMetadataController::class, 'authorizationServer'])
    ->name('oauth.metadata.authorization-server');
Route::get('/.well-known/oauth-protected-resource', [OAuthMetadataController::class, 'protectedResource'])
    ->name('oauth.metadata.protected-resource');

Route::post('/oauth/register', OAuthClientRegistrationController::class)
    ->middleware('throttle:30,1')
    ->name('oauth.register');
Route::post('/oauth/token', OAuthTokenController::class)
    ->middleware('throttle:60,1')
    ->name('oauth.token');
Route::post('/oauth/revoke', OAuthRevocationController::class)
    ->middleware('throttle:60,1')
    ->name('oauth.revoke');

Route::middleware(['api', 'session.optional', 'no-cache-auth', PreventClickjacking::class])->group(function (): void {
    Route::get('/oauth/authorize', [OAuthAuthorizationController::class, 'show'])->name('oauth.authorize');
    Route::post('/oauth/authorize', [OAuthAuthorizationController::class, 'decide'])->name('oauth.authorize.decide');
});
