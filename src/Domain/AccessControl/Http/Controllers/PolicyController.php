<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Http\Controllers;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessControlAdministration;
use Polymorph\Platform\Domain\AccessControl\Core\Models\Policy;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Polymorph\Platform\Domain\AccessControl\Http\Requests\AssignPolicyRequest;
use Polymorph\Platform\Domain\AccessControl\Http\Requests\IndexPolicyRequest;
use Polymorph\Platform\Domain\AccessControl\Http\Requests\StorePolicyRequest;
use Polymorph\Platform\Domain\AccessControl\Http\Requests\UpdatePolicyRequest;
use Polymorph\Platform\Domain\AccessControl\Http\Resources\AssignmentResource;
use Polymorph\Platform\Domain\AccessControl\Http\Resources\PolicyResource;
use Polymorph\Platform\Domain\AccessControl\Services\PolicyQueryService;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Pagination\V2\PaginatedJsonResponse;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class PolicyController extends Controller
{
    public function __construct(
        private readonly PolicyQueryService $queryService,
        private readonly AccessControlAdministration $adminService,
    ) {
    }

    public function index(IndexPolicyRequest $request): JsonResponse
    {
        $result = $this->queryService
            ->listPolicies($request->filters(), $request->pageRequest())
            ->mapItems(
                static fn (Policy $row): array => PolicyResource::make($row)->toArray($request)
            );

        return PaginatedJsonResponse::from(
            $result
        );
    }

    public function store(StorePolicyRequest $request): JsonResponse
    {
        $policy = $this->adminService->createPolicy($request->validated());

        return AdminResponse::json(['data' => PolicyResource::make($policy)->toArray($request)], 201);
    }

    public function update(UpdatePolicyRequest $request, int $policyId): JsonResponse
    {
        $policy = $this->adminService->updatePolicy($policyId, $request->validated());

        return AdminResponse::json(['data' => PolicyResource::make($policy)->toArray($request)]);
    }

    public function destroy(int $policyId): Response
    {
        $this->adminService->deletePolicy($policyId);

        return AdminResponse::noContent();
    }

    public function listAssignments(int $policyId): JsonResponse
    {
        return AdminResponse::json(['data' => $this->queryService->listAssignments($policyId)]);
    }

    public function assign(AssignPolicyRequest $request, int $policyId): JsonResponse
    {
        $assignment = $this->adminService->assign(
            $policyId,
            Subject::fromString((string) $request->validated()['subject']),
        );

        return AdminResponse::json(['data' => AssignmentResource::make($assignment)->toArray($request)], 201);
    }

    public function unassign(int $policyId, int $assignmentId): Response
    {
        $this->adminService->unassign($policyId, $assignmentId);

        return AdminResponse::noContent();
    }
}