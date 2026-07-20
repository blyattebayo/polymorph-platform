<?php

declare(strict_types=1);

use Polymorph\Platform\Domain\Media\Access\MediaCapabilities;
use Polymorph\Platform\Http\Middleware\RequireCapability;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;

return [
    [
        'kind' => RouteNodeKind::GROUP,
        'children' => [
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/media/config',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\Media\Http\Controllers\MediaController@config',
                ],
                'name' => 'admin.v1.media.config',
                'middleware' => [RequireCapability::forRoute(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/media',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\Media\Http\Controllers\MediaController@index',
                ],
                'name' => 'admin.v1.media.index',
                'middleware' => [RequireCapability::forRoute(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/media/{media}',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\Media\Http\Controllers\MediaController@show',
                ],
                'name' => 'admin.v1.media.show',
                'middleware' => [RequireCapability::forRoute(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_READ)],
            ],
        ],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/media',
        'methods' => ['POST'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\Platform\Domain\Media\Http\Controllers\MediaController@store',
        ],
        'name' => 'admin.v1.media.store',
        'middleware' => [RequireCapability::forRoute(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_WRITE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/media/bulk',
        'methods' => ['POST'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\Platform\Domain\Media\Http\Controllers\MediaController@bulkStore',
        ],
        'name' => 'admin.v1.media.bulkStore',
        'middleware' => [RequireCapability::forRoute(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_WRITE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/media/{media}',
        'methods' => ['PUT'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\Platform\Domain\Media\Http\Controllers\MediaController@update',
        ],
        'name' => 'admin.v1.media.update',
        'middleware' => [RequireCapability::forRoute(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_WRITE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/media/bulk',
        'methods' => ['DELETE'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\Platform\Domain\Media\Http\Controllers\MediaController@bulkDestroy',
        ],
        'name' => 'admin.v1.media.bulkDestroy',
        'middleware' => [RequireCapability::forRoute(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_DELETE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/media/bulk/restore',
        'methods' => ['POST'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\Platform\Domain\Media\Http\Controllers\MediaController@bulkRestore',
        ],
        'name' => 'admin.v1.media.bulkRestore',
        'middleware' => [RequireCapability::forRoute(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_WRITE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/media/bulk/force',
        'methods' => ['DELETE'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\Platform\Domain\Media\Http\Controllers\MediaController@bulkForceDestroy',
        ],
        'name' => 'admin.v1.media.bulkForceDestroy',
        'middleware' => [RequireCapability::forRoute(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_DELETE)],
    ],
    [
        'kind' => RouteNodeKind::ROUTE,
        'uri' => '/media/trash/empty',
        'methods' => ['DELETE'],
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action_meta' => [
            'action' => 'Polymorph\Platform\Domain\Media\Http\Controllers\MediaController@emptyTrash',
        ],
        'name' => 'admin.v1.media.emptyTrash',
        'middleware' => [RequireCapability::forRoute(MediaCapabilities::RESOURCE, CapabilityCatalog::ACTION_DELETE)],
    ],
];
