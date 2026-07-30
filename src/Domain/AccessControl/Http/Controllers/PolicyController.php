<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessControlAdministration;
use Polymorph\Platform\Domain\AccessControl\Core\Models\Policy;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Polymorph\Platform\Domain\AccessControl\Http\Requests\AssignPolicyRequest;
use Polymorph\Platform\Domain\AccessControl\Http\Requests\IndexPoliciesRequest;
use Polymorph\Platform\Domain\AccessControl\Http\Requests\StorePolicyRequest;
use Polymorph\Platform\Domain\AccessControl\Http\Requests\UpdatePolicyRequest;
use Polymorph\Platform\Domain\AccessControl\Http\Resources\AssignmentResource;
use Polymorph\Platform\Domain\AccessControl\Http\Resources\PolicyResource;
use Polymorph\Platform\Domain\AccessControl\Services\PolicyQueryService;
use Polymorph\Platform\Domain\AccessControl\Services\PolicyScopeAuthority;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Pagination\V2\PaginatedJsonResponse;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Symfony\Component\HttpFoundation\Response;

final class PolicyController extends Controller
{
    public function __construct(
        private readonly PolicyQueryService $queryService,
        private readonly AccessControlAdministration $adminService,
        private readonly PolicyScopeAuthority $scopeAuthority,
    ) {}

    public function index(IndexPoliciesRequest $request): JsonResponse
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
        $validated = $request->validated();

        // Нельзя выпустить политику шире собственных прав — иначе policy.manage
        // был бы фабрикой wildcard'ов.
        $this->scopeAuthority->assertCanManageScope(
            (string) $validated['resource_pattern'],
            (string) $validated['action'],
        );

        $policy = $this->adminService->createPolicy($validated);

        return AdminResponse::json(['data' => PolicyResource::make($policy)->toArray($request)], 201);
    }

    public function update(UpdatePolicyRequest $request, int $policyId): JsonResponse
    {
        $validated = $request->validated();

        // И старый скоуп (что переписываем), и новый (во что) должны быть в
        // пределах прав актора — иначе свою узкую политику можно расширить в '*'.
        $this->scopeAuthority->assertCanManagePolicies([$policyId]);
        $this->scopeAuthority->assertCanManageScope(
            (string) $validated['resource_pattern'],
            (string) $validated['action'],
        );

        $policy = $this->adminService->updatePolicy($policyId, $validated);

        return AdminResponse::json(['data' => PolicyResource::make($policy)->toArray($request)]);
    }

    public function destroy(int $policyId): Response
    {
        $this->scopeAuthority->assertCanManagePolicies([$policyId]);

        $this->adminService->deletePolicy($policyId);

        return AdminResponse::noContent();
    }

    public function listAssignments(int $policyId): JsonResponse
    {
        return AdminResponse::json(['data' => $this->queryService->listAssignments($policyId)]);
    }

    public function assign(AssignPolicyRequest $request, int $policyId): JsonResponse
    {
        $this->scopeAuthority->assertCanManagePolicies([$policyId]);

        $assignment = $this->adminService->assign(
            $policyId,
            Subject::fromString((string) $request->validated()['subject']),
        );

        return AdminResponse::json(['data' => AssignmentResource::make($assignment)->toArray($request)], 201);
    }

    public function unassign(int $policyId, int $assignmentId): Response
    {
        // Снятие тоже в пределах собственных прав: иначе policy.assign мог бы
        // снять wildcard с role:system.admin и обезглавить админов.
        $this->scopeAuthority->assertCanManagePolicies([$policyId]);

        $this->adminService->unassign($policyId, $assignmentId);

        return AdminResponse::noContent();
    }
}
