<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases;

use Polymorph\Platform\Domain\Auth\Application\DTO\PersonalAccessTokenView;
use Polymorph\Platform\Domain\Auth\Core\Contracts\PersonalAccessTokenRepository;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Domain\Users\Queries\FindUserByIdQuery;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageMeta;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageResult;

final class AdminListPersonalAccessTokens
{
    public function __construct(
        private readonly PersonalAccessTokenRepository $repository,
        private readonly FindUserByIdQuery $findUserByIdQuery,
    ) {}

    /**
     * @param  array{user_id?: int|null, status?: string|null}  $filters
     */
    public function execute(PageRequest $pagination, array $filters = []): PageResult
    {
        $paginator = $this->repository->paginateAll(
            page: $pagination->page,
            perPage: $pagination->perPage,
            filters: $filters,
        );

        $usersById = [];

        $items = collect($paginator->items())->map(function ($token) use (&$usersById): PersonalAccessTokenView {
            $userId = (int) $token->user_id;
            if (! array_key_exists($userId, $usersById)) {
                $user = $this->findUserByIdQuery->execute($userId);
                $usersById[$userId] = $user instanceof User
                    ? ['id' => $user->userId(), 'name' => (string) $user->name, 'email' => (string) $user->email]
                    : null;
            }

            return PersonalAccessTokenView::fromModel($token, $usersById[$userId]);
        })->map(static fn (PersonalAccessTokenView $view): array => $view->toArray())->all();

        return new PageResult(
            items: $items,
            meta: new PageMeta(
                currentPage: (int) $paginator->currentPage(),
                lastPage: (int) $paginator->lastPage(),
                perPage: (int) $paginator->perPage(),
                total: (int) $paginator->total(),
            ),
        );
    }
}
