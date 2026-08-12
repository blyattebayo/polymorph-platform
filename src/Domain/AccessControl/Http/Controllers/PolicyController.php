<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessControlAdministration;
use Polymorph\Platform\Domain\AccessControl\Core\Models\Policy;
use Polymorph\Platform\Domain\AccessControl\Http\Requests\IndexPoliciesRequest;
use Polymorph\Platform\Domain\AccessControl\Http\Requests\SavePolicyRequest;
use Polymorph\Platform\Domain\AccessControl\Http\Resources\PolicyResource;
use Polymorph\Platform\Domain\AccessControl\Infrastructure\Repositories\EloquentPolicyRepository;
use Polymorph\Platform\Domain\AccessControl\Services\PolicyScopeAuthority;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Pagination\V2\PaginatedJsonResponse;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\Infrastructure\Pagination\V2\LaravelPaginatorAdapter;
use Symfony\Component\HttpFoundation\Response;

final class PolicyController extends Controller
{
    public function __construct(
        private readonly EloquentPolicyRepository $policies,
        private readonly LaravelPaginatorAdapter $paginatorAdapter,
        private readonly AccessControlAdministration $adminService,
        private readonly PolicyScopeAuthority $scopeAuthority,
    ) {}

    public function index(IndexPoliciesRequest $request): JsonResponse
    {
        $result = $this->paginatorAdapter
            ->toPageResult($this->policies->paginate($request->filters(), $request->pageRequest()))
            ->mapItems(
                static fn (Policy $row): array => PolicyResource::make($row)->toArray($request)
            );

        return PaginatedJsonResponse::from(
            $result
        );
    }

    public function store(SavePolicyRequest $request): JsonResponse
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

    public function update(SavePolicyRequest $request, int $policyId): JsonResponse
    {
        $validated = $request->validated();

        // И старый скоуп (что переписываем), и новый (во что) должны быть в
        // пределах прав актора — иначе свою узкую политику можно расширить в '*'.
        $this->scopeAuthority->assertCanManagePolicies([$policyId]);
        $this->scopeAuthority->assertCanManageScope(
            (string) $validated['resource_pattern'],
            (string) $validated['action'],
        );
        // Правка бьёт по всем, кому политика уже назначена: сменив effect на
        // deny, её можно было превратить в оружие против привилегированных.
        $this->scopeAuthority->assertCanRewritePolicyForSubjects($policyId);

        $policy = $this->adminService->updatePolicy($policyId, $validated);

        return AdminResponse::json(['data' => PolicyResource::make($policy)->toArray($request)]);
    }

    public function destroy(int $policyId): Response
    {
        $this->scopeAuthority->assertCanManagePolicies([$policyId]);
        // Удаление снимает политику со всех её субъектов — для allow это
        // урезание доступа.
        $this->scopeAuthority->assertCanRewritePolicyForSubjects($policyId);

        $this->adminService->deletePolicy($policyId);

        return AdminResponse::noContent();
    }
}
