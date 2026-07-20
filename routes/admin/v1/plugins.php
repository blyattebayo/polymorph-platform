<?php

declare(strict_types=1);

use Polymorph\Platform\Domain\Extensions\Access\ExtensionsCapabilities;
use Polymorph\Platform\Http\Middleware\RequireCapability;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;

return [
    [
        'kind' => RouteNodeKind::GROUP,
        'prefix' => 'plugins',
        'children' => [
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Extensions\\Http\\Controllers\\ExtensionAdminController@index',
                ],
                'name' => 'admin.v1.plugins.index',
                'middleware' => [RequireCapability::forRoute(ExtensionsCapabilities::MANAGE)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/enable',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Extensions\\Http\\Controllers\\ExtensionAdminController@enable',
                ],
                'name' => 'admin.v1.plugins.enable',
                'middleware' => [RequireCapability::forRoute(ExtensionsCapabilities::MANAGE)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/disable',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Extensions\\Http\\Controllers\\ExtensionAdminController@disable',
                ],
                'name' => 'admin.v1.plugins.disable',
                'middleware' => [RequireCapability::forRoute(ExtensionsCapabilities::MANAGE)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/update',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Extensions\\Http\\Controllers\\ExtensionAdminController@update',
                ],
                'name' => 'admin.v1.plugins.update',
                'middleware' => [RequireCapability::forRoute(ExtensionsCapabilities::MANAGE)],
            ],
        ],
    ],
];

