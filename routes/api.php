<?php

declare(strict_types=1);

use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Http\Middleware\EnsureSessionCredential;
use Polymorph\Platform\Http\Middleware\OptionalApiAuth;

/**
 * Декларативные маршруты для публичного API.
 *
 * Эти маршруты загружаются автоматически и имеют приоритет над маршрутами из БД.
 * Используются для статических системных маршрутов, которые не должны изменяться через UI.
 *
 * @return array<int, array<string, mixed>>
 */
return [
    // Группа для публичных API маршрутов
    // Полный префикс: api/v1
    // Middleware группа 'api' применяется в RouteServiceProvider через Route::middleware('api')
    // sort_order = -999 (второй в порядке регистрации)
    [
        'kind' => RouteNodeKind::GROUP,
        'sort_order' => -999,
        'prefix' => 'api/v1',
        'middleware' => ['api'],
        'children' => [
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/validation-rules',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Support\\Validation\\Http\\ValidationRulesController',
                ],
                'name' => 'api.v1.validation-rules',
            ],
            // Authentication endpoints
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/auth/register',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\Auth\Http\Controllers\AuthController@register',
                ],
                'name' => 'api.auth.register',
                'middleware' => ['throttle:auth-register', 'no-cache-auth'],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/auth/login',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\Auth\Http\Controllers\AuthController@login',
                ],
                'name' => 'api.auth.login',
                'middleware' => ['throttle:auth-login', 'no-cache-auth'],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/auth/refresh',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\Auth\Http\Controllers\AuthController@refresh',
                ],
                'name' => 'api.auth.refresh',
                'middleware' => ['throttle:auth-refresh', 'no-cache-auth'],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/auth/password/forgot',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Auth\\Http\\Controllers\\PasswordResetController@forgot',
                ],
                'name' => 'api.auth.password.forgot',
                'middleware' => ['throttle:auth-password-reset', 'no-cache-auth'],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/auth/password/reset',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Auth\\Http\\Controllers\\PasswordResetController@reset',
                ],
                'name' => 'api.auth.password.reset',
                'middleware' => ['throttle:auth-password-reset', 'no-cache-auth'],
            ],
            // Email-верификация. verify открывается из письма (подписанная ссылка),
            // отвечает редиректом в SPA; resend — для залогиненного пользователя.
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/auth/email/verify/{id}/{hash}',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Auth\\Http\\Controllers\\EmailVerificationController@verify',
                ],
                'name' => 'verification.verify',
                'middleware' => ['throttle:30,1'],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/auth/email/verification-notification',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Auth\\Http\\Controllers\\EmailVerificationController@resend',
                ],
                'name' => 'api.auth.email.resend',
                'middleware' => ['auth:api', 'throttle:auth-verify-resend', 'no-cache-auth'],
            ],
            [
                'kind' => RouteNodeKind::GROUP,
                'prefix' => 'me',
                'middleware' => ['auth:api', EnsureSessionCredential::ALIAS, 'no-cache-auth'],
                'children' => [
                    [
                        'kind' => RouteNodeKind::ROUTE,
                        'uri' => '/sessions',
                        'methods' => ['GET'],
                        'action_type' => RouteNodeActionType::CONTROLLER,
                        'action_meta' => [
                            'action' => 'Polymorph\\Platform\\Domain\\Auth\\Http\\Controllers\\MeAuthSessionController@index',
                        ],
                        'name' => 'api.me.sessions.index',
                    ],
                    [
                        'kind' => RouteNodeKind::ROUTE,
                        'uri' => '/sessions/{sessionId}',
                        'methods' => ['DELETE'],
                        'action_type' => RouteNodeActionType::CONTROLLER,
                        'action_meta' => [
                            'action' => 'Polymorph\\Platform\\Domain\\Auth\\Http\\Controllers\\MeAuthSessionController@destroy',
                        ],
                        'name' => 'api.me.sessions.destroy',
                        'where' => ['sessionId' => '[0-9]+'],
                    ],
                    [
                        'kind' => RouteNodeKind::ROUTE,
                        'uri' => '/personal-access-tokens',
                        'methods' => ['GET'],
                        'action_type' => RouteNodeActionType::CONTROLLER,
                        'action_meta' => [
                            'action' => 'Polymorph\\Platform\\Domain\\Auth\\Http\\Controllers\\MePersonalAccessTokenController@index',
                        ],
                        'name' => 'api.me.personal-access-tokens.index',
                    ],
                    [
                        'kind' => RouteNodeKind::ROUTE,
                        'uri' => '/personal-access-tokens',
                        'methods' => ['POST'],
                        'action_type' => RouteNodeActionType::CONTROLLER,
                        'action_meta' => [
                            'action' => 'Polymorph\\Platform\\Domain\\Auth\\Http\\Controllers\\MePersonalAccessTokenController@store',
                        ],
                        'name' => 'api.me.personal-access-tokens.store',
                        'middleware' => ['throttle:pat-create'],
                    ],
                    [
                        'kind' => RouteNodeKind::ROUTE,
                        'uri' => '/personal-access-tokens/{tokenId}',
                        'methods' => ['DELETE'],
                        'action_type' => RouteNodeActionType::CONTROLLER,
                        'action_meta' => [
                            'action' => 'Polymorph\\Platform\\Domain\\Auth\\Http\\Controllers\\MePersonalAccessTokenController@destroy',
                        ],
                        'name' => 'api.me.personal-access-tokens.destroy',
                        'where' => ['tokenId' => '[0-9]+'],
                    ],
                ],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/auth/logout',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\Auth\Http\Controllers\AuthController@logout',
                ],
                'name' => 'api.auth.logout',
                'middleware' => ['auth:api', 'no-cache-auth'],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/auth/current',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Auth\\Http\\Controllers\\AuthController@current',
                ],
                'name' => 'api.auth.current',
                'middleware' => ['auth:api', 'no-cache-auth'],
            ],
            // Configured navigation menu by key (FE owns defaults and ACL filtering).
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/menu/{key}',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Menu\\Http\\Controllers\\MenuController@show',
                ],
                'name' => 'api.v1.menu.show',
                'where' => ['key' => '[a-z][a-z0-9_]*'],
                'middleware' => ['auth:api'],
            ],
            // Public media access
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/media/{id}',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\Media\Http\Controllers\MediaPreviewController@show',
                ],
                'name' => 'api.v1.media.show',
                'middleware' => [OptionalApiAuth::ALIAS],
            ],
        ],
    ],
];

