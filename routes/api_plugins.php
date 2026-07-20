<?php

declare(strict_types=1);

use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;

return [
    [
        'kind' => RouteNodeKind::GROUP,
        'sort_order' => -997,
        'prefix' => 'api/v1/plugins',
        'middleware' => ['api', 'auth:api'],
        'children' => [
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/frontend-manifest',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\\Platform\\Domain\\Extensions\\Http\\Controllers\\ExtensionFrontendController@index',
                ],
                'name' => 'api.v1.plugins.frontend-manifest',
            ],
        ],
    ],
];

