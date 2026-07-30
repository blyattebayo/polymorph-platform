<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessControlAdministration;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Polymorph\Platform\Domain\AccessControl\Http\Requests\IndexAssignmentsRequest;
use Polymorph\Platform\Domain\AccessControl\Http\Requests\SetAssignmentsRequest;
use Polymorph\Platform\Domain\AccessControl\Services\PolicyQueryService;
use Polymorph\Platform\Domain\AccessControl\Services\PolicyScopeAuthority;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;

final class AssignmentController extends Controller
{
    public function __construct(
        private readonly AccessControlAdministration $adminService,
        private readonly PolicyQueryService $queryService,
        private readonly PolicyScopeAuthority $scopeAuthority,
    ) {}

    public function replace(SetAssignmentsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $subject = Subject::fromString((string) $validated['subject']);
        $policyIds = array_map(static fn (mixed $id): int => (int) $id, $validated['policy_ids']);

        // Каждая политика из диффа (добавляемая или снимаемая) должна быть в
        // пределах прав актора — до этой проверки носитель policy.assign мог
        // назначить себе wildcard и стать суперадмином. Если замена вдобавок
        // урезает субъекта (добавляет deny или снимает allow), он должен быть
        // не привилегированнее актора.
        $this->scopeAuthority->assertCanReplaceSubjectPolicies($subject, $policyIds);

        $this->adminService->setSubjectPolicies($subject, $policyIds);

        return AdminResponse::json([
            'data' => $this->queryService->listSubjectAssignments($subject),
        ]);
    }

    public function indexBySubject(IndexAssignmentsRequest $request): JsonResponse
    {
        $data = $this->queryService->listSubjectAssignments(
            Subject::fromString((string) $request->validated()['subject']),
        );

        return AdminResponse::json(['data' => $data]);
    }
}
