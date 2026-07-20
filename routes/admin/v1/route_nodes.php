<?php

declare(strict_types=1);

use Polymorph\Platform\Domain\Routing\Access\RoutingCapabilities;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Http\Middleware\RequireCapability;

return [
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/routes',
        'methods' => ['GET'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\\Platform\\Domain\\Routing\\Http\\Controllers\\RouteNodeController@index',
        ],
        'name' => 'admin.v1.routes.index',
        'middleware' => [RequireCapability::forRoute(RoutingCapabilities::ROUTE_MANAGE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/routes',
        'methods' => ['POST'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\\Platform\\Domain\\Routing\\Http\\Controllers\\RouteNodeController@store',
        ],
        'name' => 'admin.v1.routes.store',
        'middleware' => [RequireCapability::forRoute(RoutingCapabilities::ROUTE_MANAGE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/routes/reorder',
        'methods' => ['POST'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\\Platform\\Domain\\Routing\\Http\\Controllers\\RouteNodeController@reorder',
        ],
        'name' => 'admin.v1.routes.reorder',
        'middleware' => [RequireCapability::forRoute(RoutingCapabilities::ROUTE_MANAGE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/routes/{id}',
        'methods' => ['GET'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\\Platform\\Domain\\Routing\\Http\\Controllers\\RouteNodeController@show',
        ],
        'name' => 'admin.v1.routes.show',
        'where' => ['id' => '[0-9]+'],
        'middleware' => [RequireCapability::forRoute(RoutingCapabilities::ROUTE_MANAGE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/routes/{id}',
        'methods' => ['PUT'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\\Platform\\Domain\\Routing\\Http\\Controllers\\RouteNodeController@update',
        ],
        'name' => 'admin.v1.routes.update',
        'where' => ['id' => '[0-9]+'],
        'middleware' => [RequireCapability::forRoute(RoutingCapabilities::ROUTE_MANAGE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/routes/{id}',
        'methods' => ['DELETE'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\\Platform\\Domain\\Routing\\Http\\Controllers\\RouteNodeController@destroy',
        ],
        'name' => 'admin.v1.routes.destroy',
        'where' => ['id' => '[0-9]+'],
        'middleware' => [RequireCapability::forRoute(RoutingCapabilities::ROUTE_MANAGE)],
    ],
];
