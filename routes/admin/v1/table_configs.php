<?php

declare(strict_types=1);

use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Domain\TableConfig\Access\TableConfigCapabilities;
use Polymorph\Platform\Http\Middleware\RequireCapability;

return [
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/table-configs/{tableKey}/base',
        'methods' => ['GET'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\\Platform\\Domain\\TableConfig\\Http\\Controllers\\TableConfigController@showBase',
        ],
        'name' => 'admin.v1.table-configs.base.show',
        'where' => ['tableKey' => '[A-Za-z0-9._-]+'],
        'middleware' => [RequireCapability::forRoute(TableConfigCapabilities::MANAGE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/table-configs/{tableKey}/base',
        'methods' => ['PUT'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\\Platform\\Domain\\TableConfig\\Http\\Controllers\\TableConfigController@updateBase',
        ],
        'name' => 'admin.v1.table-configs.base.update',
        'where' => ['tableKey' => '[A-Za-z0-9._-]+'],
        'middleware' => [RequireCapability::forRoute(TableConfigCapabilities::MANAGE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/table-configs/{tableKey}/overrides/me',
        'methods' => ['GET'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\\Platform\\Domain\\TableConfig\\Http\\Controllers\\TableConfigController@showMyOverride',
        ],
        'name' => 'admin.v1.table-configs.overrides.me.show',
        'where' => ['tableKey' => '[A-Za-z0-9._-]+'],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/table-configs/{tableKey}/overrides/me',
        'methods' => ['PUT'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\\Platform\\Domain\\TableConfig\\Http\\Controllers\\TableConfigController@updateMyOverride',
        ],
        'name' => 'admin.v1.table-configs.overrides.me.update',
        'where' => ['tableKey' => '[A-Za-z0-9._-]+'],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/table-configs/{tableKey}/overrides/me',
        'methods' => ['DELETE'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\\Platform\\Domain\\TableConfig\\Http\\Controllers\\TableConfigController@destroyMyOverride',
        ],
        'name' => 'admin.v1.table-configs.overrides.me.destroy',
        'where' => ['tableKey' => '[A-Za-z0-9._-]+'],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/table-configs/{tableKey}/resolved',
        'methods' => ['GET'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\\Platform\\Domain\\TableConfig\\Http\\Controllers\\TableConfigController@resolved',
        ],
        'name' => 'admin.v1.table-configs.resolved',
        'where' => ['tableKey' => '[A-Za-z0-9._-]+'],
    ],
];
