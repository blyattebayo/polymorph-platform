<?php

declare(strict_types=1);

use Polymorph\Platform\Domain\Roles\Access\RolesCapabilities;
use Polymorph\Platform\Domain\Users\Access\UsersCapabilities;
use Polymorph\Platform\Http\Middleware\RequireCapability;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;

return [
    [
        'kind' => RouteNodeKind::GROUP,
        'prefix' => 'roles',
        'children' => [
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Roles\\Http\\Controllers\\RoleController@index',
                ],
                'name' => 'admin.v1.roles.index',
                'middleware' => [RequireCapability::forRoute(RolesCapabilities::READ)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Roles\\Http\\Controllers\\RoleController@store',
                ],
                'name' => 'admin.v1.roles.store',
                'middleware' => [RequireCapability::forRoute(RolesCapabilities::LIFECYCLE)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/bulk',
                'methods' => ['DELETE'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Roles\\Http\\Controllers\\RoleController@bulkDestroy',
                ],
                'name' => 'admin.v1.roles.bulkDestroy',
                'middleware' => [RequireCapability::forRoute(RolesCapabilities::LIFECYCLE)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/{roleId}',
                'methods' => ['DELETE'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Roles\\Http\\Controllers\\RoleController@destroy',
                ],
                'name' => 'admin.v1.roles.destroy',
                'where' => ['roleId' => '[0-9]+'],
                'middleware' => [RequireCapability::forRoute(RolesCapabilities::LIFECYCLE)],
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
                'uri' => '/',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Users\\Http\\Controllers\\UserController@index',
                ],
                'name' => 'admin.v1.users.index',
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Users\\Http\\Controllers\\UserController@store',
                ],
                'name' => 'admin.v1.users.store',
                'middleware' => [RequireCapability::forRoute(UsersCapabilities::LIFECYCLE)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/{userId}',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Users\\Http\\Controllers\\UserController@show',
                ],
                'name' => 'admin.v1.users.show',
                'where' => ['userId' => '[0-9]+'],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/{userId}',
                'methods' => ['PUT'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Users\\Http\\Controllers\\UserController@update',
                ],
                'name' => 'admin.v1.users.update',
                'where' => ['userId' => '[0-9]+'],
                'middleware' => [RequireCapability::forRoute(UsersCapabilities::LIFECYCLE)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/{userId}/password',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Users\\Http\\Controllers\\UserController@setPassword',
                ],
                'name' => 'admin.v1.users.password',
                'where' => ['userId' => '[0-9]+'],
                'middleware' => [RequireCapability::forRoute(UsersCapabilities::LIFECYCLE)],
            ],
        ],
    ],
];
