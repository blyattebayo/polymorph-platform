<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Services;

use Polymorph\Platform\Domain\Roles\Core\Contracts\UserRoleReadModel;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserRepository;
use Polymorph\Platform\Domain\Users\Core\Exceptions\UserNotFoundException;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Domain\Users\Http\Resources\AdminUserResource;
use Polymorph\Platform\Domain\Users\Queries\FindUserByIdQuery;
use Polymorph\Platform\Infrastructure\Pagination\V2\LaravelPaginatorAdapter;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageResult;

final class AdminUserReadService
{
    public function __construct(
        private readonly UserRoleReadModel $userRoleReadModel,
        private readonly UserRepository $userRepository,
        private readonly FindUserByIdQuery $findUserByIdQuery,
        private readonly LaravelPaginatorAdapter $paginatorAdapter,
    ) {}

    public function list(PageRequest $page, ?string $search = null): PageResult
    {
        $result = $this->paginatorAdapter->toPageResult($this->userRepository->paginate($page, $search));
        $rolesByUser = $this->userRoleReadModel->rolesByUserIds(
            $result->ids(static fn (User $user): int => (int) $user->id)
        );

        return $result->mapItems(
            static fn (User $user): array => (new AdminUserResource($user, $rolesByUser[(int) $user->id] ?? []))->resolve()
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws UserNotFoundException
     */
    public function show(int $userId): array
    {
        return $this->present($this->findUserByIdQuery->executeOrFail($userId));
    }

    /**
     * @return array<string, mixed>
     */
    public function present(User $user): array
    {
        $rolesByUser = $this->userRoleReadModel->rolesByUserIds([$user->id]);

        return (new AdminUserResource($user, $rolesByUser[$user->id] ?? []))->resolve();
    }
}
