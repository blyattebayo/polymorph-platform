<?php

declare(strict_types=1);

use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Domain\Users\Access\UsersCapabilities;
use Polymorph\Platform\Http\Middleware\EnsureSessionCredential;
use Polymorph\Platform\Http\Middleware\RequireCapability;

return [
    [
        'kind' => RouteNodeKind::GROUP,
        'prefix' => 'personal-access-tokens',
        'children' => [
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Auth\\Http\\Controllers\\AdminPersonalAccessTokenController@indexAll',
                ],
                'name' => 'admin.v1.personal-access-tokens.index',
                'middleware' => [RequireCapability::forRoute(UsersCapabilities::READ)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/{tokenId}',
                'methods' => ['DELETE'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Auth\\Http\\Controllers\\AdminPersonalAccessTokenController@destroy',
                ],
                'name' => 'admin.v1.personal-access-tokens.destroy',
                'where' => ['tokenId' => '[0-9]+'],
                'middleware' => [RequireCapability::forRoute(UsersCapabilities::LIFECYCLE), EnsureSessionCredential::ALIAS],
            ],
        ],
    ],
    [
        'kind' => RouteNodeKind::GROUP,
        'prefix' => 'users',
        'middleware' => [RequireCapability::forRoute(UsersCapabilities::READ)],
        'children' => [
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/{userId}/personal-access-tokens',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Auth\\Http\\Controllers\\AdminPersonalAccessTokenController@indexForUser',
                ],
                'name' => 'admin.v1.users.personal-access-tokens.index',
                'where' => ['userId' => '[0-9]+'],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/{userId}/personal-access-tokens',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Auth\\Http\\Controllers\\AdminPersonalAccessTokenController@storeForUser',
                ],
                'name' => 'admin.v1.users.personal-access-tokens.store',
                'where' => ['userId' => '[0-9]+'],
                'middleware' => ['throttle:pat-create', RequireCapability::forRoute(UsersCapabilities::LIFECYCLE), EnsureSessionCredential::ALIAS],
            ],
        ],
    ],
];
