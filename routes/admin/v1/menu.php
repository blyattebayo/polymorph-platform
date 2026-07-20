<?php

declare(strict_types=1);

use Polymorph\Platform\Domain\Menu\Access\MenuCapabilities;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Http\Middleware\RequireCapability;

$manage = [RequireCapability::forRoute(MenuCapabilities::MANAGE)];
$keyConstraint = ['key' => '[a-z][a-z0-9_]*'];

return [
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/menu/{key}',
        'methods' => ['GET'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => ['action' => 'Polymorph\\Platform\\Domain\\Menu\\Http\\Controllers\\MenuAdminController@show'],
        'name' => 'admin.v1.menu.show',
        'where' => $keyConstraint,
        'middleware' => $manage,
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/menu/{key}',
        'methods' => ['PUT'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => ['action' => 'Polymorph\\Platform\\Domain\\Menu\\Http\\Controllers\\MenuAdminController@update'],
        'name' => 'admin.v1.menu.update',
        'where' => $keyConstraint,
        'middleware' => $manage,
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/menu/{key}',
        'methods' => ['DELETE'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => ['action' => 'Polymorph\\Platform\\Domain\\Menu\\Http\\Controllers\\MenuAdminController@destroy'],
        'name' => 'admin.v1.menu.destroy',
        'where' => $keyConstraint,
        'middleware' => $manage,
    ],
];
