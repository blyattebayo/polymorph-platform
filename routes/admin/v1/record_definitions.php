<?php

declare(strict_types=1);

use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Domain\SchemaModel\Access\SchemaCapabilities;
use Polymorph\Platform\Http\Middleware\RequireCapability;

return [
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/record-definitions',
        'methods' => ['POST'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\\Platform\\Domain\\RecordDefinitions\\Http\\Controllers\\RecordDefinitionController@store',
        ],
        'name' => 'admin.v1.record-definitions.store',
        'middleware' => [RequireCapability::forRoute(SchemaCapabilities::MANAGE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/record-definitions',
        'methods' => ['GET'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\\Platform\\Domain\\RecordDefinitions\\Http\\Controllers\\RecordDefinitionController@index',
        ],
        'name' => 'admin.v1.record-definitions.index',
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/record-definitions/{id}',
        'methods' => ['GET'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\\Platform\\Domain\\RecordDefinitions\\Http\\Controllers\\RecordDefinitionController@show',
        ],
        'name' => 'admin.v1.record-definitions.show',
        'where' => ['id' => '[0-9]+'],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/record-definitions/{id}',
        'methods' => ['PUT'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\\Platform\\Domain\\RecordDefinitions\\Http\\Controllers\\RecordDefinitionController@update',
        ],
        'name' => 'admin.v1.record-definitions.update',
        'where' => ['id' => '[0-9]+'],
        'middleware' => [RequireCapability::forRoute(SchemaCapabilities::MANAGE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/record-definitions/{id}',
        'methods' => ['DELETE'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\\Platform\\Domain\\RecordDefinitions\\Http\\Controllers\\RecordDefinitionController@destroy',
        ],
        'name' => 'admin.v1.record-definitions.destroy',
        'where' => ['id' => '[0-9]+'],
        'middleware' => [RequireCapability::forRoute(SchemaCapabilities::MANAGE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/record-definitions/{record_definition_id}/form-config/{schema}',
        'methods' => ['GET'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\Platform\Domain\EntryView\Http\Controllers\EntryViewController@show',
        ],
        'name' => 'admin.v1.record-definitions.form-config.show',
        'where' => ['record_definition_id' => '[0-9]+'],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/record-definitions/{record_definition_id}/form-config/{schema}',
        'methods' => ['PUT'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\Platform\Domain\EntryView\Http\Controllers\EntryViewController@update',
        ],
        'name' => 'admin.v1.record-definitions.form-config.update',
        'where' => ['record_definition_id' => '[0-9]+'],
        'middleware' => [RequireCapability::forRoute(SchemaCapabilities::MANAGE)],
    ],
];
