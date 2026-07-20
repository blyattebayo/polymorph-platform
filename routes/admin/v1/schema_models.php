<?php

declare(strict_types=1);

use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Domain\SchemaModel\Access\SchemaCapabilities;
use Polymorph\Platform\Http\Middleware\RequireCapability;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

return [
    [
        'kind' => RouteNodeKind::GROUP,
        'prefix' => 'schema-models',
        'children' => [
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\SchemaModel\Http\Controllers\SchemaController@index',
                ],
                'name' => 'admin.v1.schema-models.index',
                'middleware' => [
                    RequireCapability::forRoute(
                        SchemaCapabilities::RESOURCE,
                        CapabilityCatalog::ACTION_READ,
                    ),
                ],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\SchemaModel\Http\Controllers\SchemaController@store',
                ],
                'name' => 'admin.v1.schema-models.store',
                'middleware' => [RequireCapability::forRoute(SchemaCapabilities::MANAGE)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/bulk',
                'methods' => ['DELETE'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\SchemaModel\Http\Controllers\SchemaController@bulkDestroy',
                ],
                'name' => 'admin.v1.schema-models.bulkDestroy',
                'middleware' => [RequireCapability::forRoute(SchemaCapabilities::MANAGE)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/{schema}',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\SchemaModel\Http\Controllers\SchemaController@show',
                ],
                'name' => 'admin.v1.schema-models.show',
                'where' => ['schema' => '[0-9]+'],
                'middleware' => [
                    RequireCapability::forRoute(
                        SchemaCapabilities::RESOURCE,
                        CapabilityCatalog::ACTION_READ,
                    ),
                ],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/{schema}',
                'methods' => ['PUT'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\SchemaModel\Http\Controllers\SchemaController@update',
                ],
                'name' => 'admin.v1.schema-models.update',
                'where' => ['schema' => '[0-9]+'],
                'middleware' => [RequireCapability::forRoute(SchemaCapabilities::MANAGE)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/{schema}',
                'methods' => ['DELETE'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\SchemaModel\Http\Controllers\SchemaController@destroy',
                ],
                'name' => 'admin.v1.schema-models.destroy',
                'where' => ['schema' => '[0-9]+'],
                'middleware' => [RequireCapability::forRoute(SchemaCapabilities::MANAGE)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/{schema}/tree',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\SchemaModel\Http\Controllers\SchemaController@tree',
                ],
                'name' => 'admin.v1.schema-models.tree',
                'where' => ['schema' => '[0-9]+'],
                'middleware' => [
                    RequireCapability::forRoute(
                        SchemaCapabilities::RESOURCE,
                        CapabilityCatalog::ACTION_READ,
                    ),
                ],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/{schema}/usage',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\SchemaModel\Http\Controllers\SchemaController@usage',
                ],
                'name' => 'admin.v1.schema-models.usage',
                'where' => ['schema' => '[0-9]+'],
                'middleware' => [
                    RequireCapability::forRoute(
                        SchemaCapabilities::RESOURCE,
                        CapabilityCatalog::ACTION_READ,
                    ),
                ],
            ],
        ],
    ],
];
