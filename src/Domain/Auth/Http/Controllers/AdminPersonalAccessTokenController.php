<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Auth\Application\DTO\CreatePersonalAccessTokenCommand;
use Polymorph\Platform\Domain\Auth\Application\UseCases\AdminCreatePersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Application\UseCases\AdminListPersonalAccessTokens;
use Polymorph\Platform\Domain\Auth\Application\UseCases\AdminRevokePersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Application\UseCases\ListOwnPersonalAccessTokens;
use Polymorph\Platform\Domain\Auth\Http\Requests\IndexAdminPersonalAccessTokenRequest;
use Polymorph\Platform\Domain\Auth\Http\Requests\StorePersonalAccessTokenRequest;
use Polymorph\Platform\Domain\Auth\Http\Resources\CreatedPersonalAccessTokenResource;
use Polymorph\Platform\Domain\Auth\Http\Resources\PersonalAccessTokenResource;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Domain\Users\Queries\FindUserByIdQuery;
use Polymorph\Platform\Http\Pagination\V2\PaginatedJsonResponse;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;

final class AdminPersonalAccessTokenController
{
    public function __construct(
        private readonly AdminListPersonalAccessTokens $listAll,
        private readonly ListOwnPersonalAccessTokens $listForUser,
        private readonly AdminCreatePersonalAccessToken $createForUser,
        private readonly AdminRevokePersonalAccessToken $revoke,
        private readonly FindUserByIdQuery $findUserByIdQuery,
        private readonly AuthenticationContext $auth,
    ) {}

    public function indexAll(IndexAdminPersonalAccessTokenRequest $request): JsonResponse
    {
        return PaginatedJsonResponse::from(
            $this->listAll->execute(
                PageRequest::fromValidated($request->validated()),
                $request->filters(),
            ),
        );
    }

    public function indexForUser(int $userId): JsonResponse
    {
        $this->requireUser($userId);

        return AdminResponse::json([
            'data' => PersonalAccessTokenResource::collection(
                $this->listForUser->execute($userId),
            ),
        ]);
    }

    public function storeForUser(StorePersonalAccessTokenRequest $request, int $userId): JsonResponse
    {
        $targetUser = $this->requireUser($userId);
        $createdBy = $this->auth->requireUser();
        $validated = $request->validated();

        $created = $this->createForUser->execute(
            new CreatePersonalAccessTokenCommand(
                userId: $targetUser->userId(),
                name: (string) $validated['name'],
                createdByUserId: $createdBy->userId(),
                ttl: isset($validated['ttl']) ? (string) $validated['ttl'] : null,
            ),
            $targetUser,
            $this->auth->credential(),
        );

        return AdminResponse::json([
            'data' => CreatedPersonalAccessTokenResource::fromResult($created),
        ], 201);
    }

    public function destroy(int $tokenId): JsonResponse
    {
        $this->revoke->execute($tokenId, $this->auth->credential());

        return AdminResponse::json([
            'data' => ['revoked' => true],
        ]);
    }

    private function requireUser(int $userId): User
    {
        $user = $this->findUserByIdQuery->execute($userId);
        abort_unless($user instanceof User, 404);

        return $user;
    }
}
