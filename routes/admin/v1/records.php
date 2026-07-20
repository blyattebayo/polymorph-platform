<?php

declare(strict_types=1);

use Polymorph\Platform\Domain\Records\Access\RecordsCapabilities;
use Polymorph\Platform\Http\Middleware\RequireCapability;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;

return [
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/records',
        'methods' => ['GET'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\Platform\Domain\Records\Http\Controllers\RecordController@index',
        ],
        'name' => 'admin.v1.records.index',
        'middleware' => [RequireCapability::forRoute(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/records',
        'methods' => ['POST'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\Platform\Domain\Records\Http\Controllers\RecordController@store',
        ],
        'name' => 'admin.v1.records.store',
        'middleware' => [RequireCapability::forRoute(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_WRITE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/records/hydrate',
        'methods' => ['POST'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\Platform\Domain\Records\Http\Controllers\RecordController@hydrate',
        ],
        'name' => 'admin.v1.records.hydrate',
        'middleware' => [RequireCapability::forRoute(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/records/{id}',
        'methods' => ['GET'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\Platform\Domain\Records\Http\Controllers\RecordController@show',
        ],
        'name' => 'admin.v1.records.show',
        'middleware' => [RequireCapability::forRoute(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/records/{id}',
        'methods' => ['PUT'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\Platform\Domain\Records\Http\Controllers\RecordController@update',
        ],
        'name' => 'admin.v1.records.update',
        'middleware' => [RequireCapability::forRoute(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_WRITE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/records/{id}',
        'methods' => ['DELETE'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\Platform\Domain\Records\Http\Controllers\RecordController@destroy',
        ],
        'name' => 'admin.v1.records.destroy',
        'middleware' => [RequireCapability::forRoute(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_DELETE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/records/{id}/restore',
        'methods' => ['POST'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\Platform\Domain\Records\Http\Controllers\RecordController@restore',
        ],
        'name' => 'admin.v1.records.restore',
        'middleware' => [RequireCapability::forRoute(RecordsCapabilities::RESOURCE, CapabilityCatalog::ACTION_WRITE)],
    ],
];
