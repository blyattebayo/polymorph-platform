<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Users\Http\Requests\IndexUsersRequest;
use Polymorph\Platform\Domain\Users\Http\Requests\SetUserPasswordRequest;
use Polymorph\Platform\Domain\Users\Http\Requests\StoreUserRequest;
use Polymorph\Platform\Domain\Users\Http\Requests\UpdateUserRequest;
use Polymorph\Platform\Domain\Users\Services\AdminUserManagementService;
use Polymorph\Platform\Domain\Users\Services\AdminUserReadService;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Pagination\V2\PaginatedJsonResponse;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\SharedKernel\Identity\CurrentActorResolver;
use Symfony\Component\HttpFoundation\Response;

final class UserController extends Controller
{
    public function __construct(
        private readonly AdminUserManagementService $adminUserManagementService,
        private readonly AdminUserReadService $adminUserReadService,
        private readonly CurrentActorResolver $currentActor,
    ) {}

    public function index(IndexUsersRequest $request): JsonResponse
    {
        return PaginatedJsonResponse::from($this->adminUserReadService->list(
            $request->pageRequest(),
            $request->searchQuery(),
            $request->excludesSelf() ? $this->currentActor->actor()?->userId() : null,
        ));
    }

    public function show(int $userId): JsonResponse
    {
        return AdminResponse::json([
            'data' => $this->adminUserReadService->show($userId),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->adminUserManagementService->create($request->validated());

        return AdminResponse::json([
            'data' => $this->adminUserReadService->present($user),
        ], 201);
    }

    public function update(UpdateUserRequest $request, int $userId): JsonResponse
    {
        $updatedUser = $this->adminUserManagementService->update($userId, $request->validated());

        return AdminResponse::json([
            'data' => $this->adminUserReadService->present($updatedUser),
        ]);
    }

    public function setPassword(SetUserPasswordRequest $request, int $userId): Response
    {
        $this->adminUserManagementService->setPassword($userId, (string) $request->validated()['password']);

        return AdminResponse::noContent();
    }
}
