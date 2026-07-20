<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleRepository;
use Polymorph\Platform\Domain\Roles\Core\Models\Role;
use Polymorph\Platform\Domain\Roles\Http\Requests\BulkDeleteRolesRequest;
use Polymorph\Platform\Domain\Roles\Http\Requests\IndexRolesRequest;
use Polymorph\Platform\Domain\Roles\Http\Requests\StoreRoleRequest;
use Polymorph\Platform\Domain\Roles\Http\Resources\RoleResource;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Pagination\V2\PaginatedJsonResponse;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\Infrastructure\Pagination\V2\LaravelPaginatorAdapter;
use Symfony\Component\HttpFoundation\Response;

final class RoleController extends Controller
{
    public function __construct(
        private readonly RoleRepository $roles,
        private readonly LaravelPaginatorAdapter $paginatorAdapter,
    ) {}

    public function index(IndexRolesRequest $request): JsonResponse
    {
        $result = $this->paginatorAdapter
            ->toPageResult($this->roles->paginate($request->pageRequest()))
            ->mapItems(
                static fn (Role $role): array => RoleResource::make($role)->toArray($request)
            );

        return PaginatedJsonResponse::from(
            $result
        );
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $role = $this->roles->create(
            trim((string) $validated['code']),
            trim((string) $validated['name']),
        );

        return AdminResponse::json(['data' => RoleResource::make($role)->toArray($request)], 201);
    }

    public function destroy(int $roleId): Response
    {
        $this->roles->deleteOne($roleId);

        return AdminResponse::noContent();
    }

    public function bulkDestroy(BulkDeleteRolesRequest $request): Response
    {
        $this->roles->deleteByIds($request->validated('ids'));

        return AdminResponse::noContent();
    }
}
