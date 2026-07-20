<?php

declare(strict_types=1);

use Polymorph\Platform\Domain\AccessControl\Access\AccessControlCapabilities;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Http\Middleware\RequireCapability;

return [
    [
        'kind' => RouteNodeKind::GROUP,
        'prefix' => 'acl',
        'children' => [
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/policies',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\AccessControl\Http\Controllers\PolicyController@index',
                ],
                'name' => 'admin.v1.acl.policies.index',
                'middleware' => [RequireCapability::forRoute(AccessControlCapabilities::POLICY_MANAGE)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/policies',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\AccessControl\Http\Controllers\PolicyController@store',
                ],
                'name' => 'admin.v1.acl.policies.store',
                'middleware' => [RequireCapability::forRoute(AccessControlCapabilities::POLICY_MANAGE)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/policies/{policyId}',
                'methods' => ['PUT'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\AccessControl\Http\Controllers\PolicyController@update',
                ],
                'name' => 'admin.v1.acl.policies.update',
                'where' => ['policyId' => '[0-9]+'],
                'middleware' => [RequireCapability::forRoute(AccessControlCapabilities::POLICY_MANAGE)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/policies/{policyId}',
                'methods' => ['DELETE'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\AccessControl\Http\Controllers\PolicyController@destroy',
                ],
                'name' => 'admin.v1.acl.policies.destroy',
                'where' => ['policyId' => '[0-9]+'],
                'middleware' => [RequireCapability::forRoute(AccessControlCapabilities::POLICY_MANAGE)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/policies/{policyId}/assignments',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\AccessControl\Http\Controllers\PolicyController@listAssignments',
                ],
                'name' => 'admin.v1.acl.policies.assignments.index',
                'where' => ['policyId' => '[0-9]+'],
                'middleware' => [RequireCapability::forRoute(AccessControlCapabilities::POLICY_ASSIGN)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/policies/{policyId}/assignments',
                'methods' => ['POST'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\AccessControl\Http\Controllers\PolicyController@assign',
                ],
                'name' => 'admin.v1.acl.policies.assignments.store',
                'where' => ['policyId' => '[0-9]+'],
                'middleware' => [RequireCapability::forRoute(AccessControlCapabilities::POLICY_ASSIGN)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/policies/{policyId}/assignments/{assignmentId}',
                'methods' => ['DELETE'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\AccessControl\Http\Controllers\PolicyController@unassign',
                ],
                'name' => 'admin.v1.acl.policies.assignments.destroy',
                'where' => ['policyId' => '[0-9]+', 'assignmentId' => '[0-9]+'],
                'middleware' => [RequireCapability::forRoute(AccessControlCapabilities::POLICY_ASSIGN)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/capabilities',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\AccessControl\Http\Controllers\CapabilityCatalogController@index',
                ],
                'name' => 'admin.v1.acl.capabilities.index',
                'middleware' => [RequireCapability::forRoute(AccessControlCapabilities::POLICY_ASSIGN)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/assignments',
                'methods' => ['PUT'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\AccessControl\Http\Controllers\AssignmentController@replace',
                ],
                'name' => 'admin.v1.acl.assignments.replace',
                'middleware' => [RequireCapability::forRoute(AccessControlCapabilities::POLICY_ASSIGN)],
            ],
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/assignments',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\AccessControl\Http\Controllers\AssignmentController@indexBySubject',
                ],
                'name' => 'admin.v1.acl.assignments.index',
                'middleware' => [RequireCapability::forRoute(AccessControlCapabilities::POLICY_ASSIGN)],
            ],
        ],
    ],
];
