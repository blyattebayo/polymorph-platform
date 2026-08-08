<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Auth\Application\DTO\CreatePersonalAccessTokenCommand;
use Polymorph\Platform\Domain\Auth\Application\UseCases\CreateOwnPersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Application\UseCases\ListOwnPersonalAccessTokens;
use Polymorph\Platform\Domain\Auth\Application\UseCases\RevokeOwnPersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Http\Requests\StorePersonalAccessTokenRequest;
use Polymorph\Platform\Domain\Auth\Http\Resources\CreatedPersonalAccessTokenResource;
use Polymorph\Platform\Domain\Auth\Http\Resources\PersonalAccessTokenResource;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;

final class MePersonalAccessTokenController
{
    public function __construct(
        private readonly ListOwnPersonalAccessTokens $listOwn,
        private readonly CreateOwnPersonalAccessToken $createOwn,
        private readonly RevokeOwnPersonalAccessToken $revokeOwn,
        private readonly AuthenticationContext $auth,
    ) {}

    public function index(): JsonResponse
    {
        $user = $this->auth->requireUser();

        return AdminResponse::json([
            'data' => PersonalAccessTokenResource::collection(
                $this->listOwn->execute($user->userId()),
            ),
        ]);
    }

    public function store(StorePersonalAccessTokenRequest $request): JsonResponse
    {
        $user = $this->auth->requireUser();
        $validated = $request->validated();

        $created = $this->createOwn->execute(
            new CreatePersonalAccessTokenCommand(
                userId: $user->userId(),
                name: (string) $validated['name'],
                createdByUserId: $user->userId(),
                ttl: isset($validated['ttl']) ? (string) $validated['ttl'] : null,
            ),
            $this->auth->credential(),
        );

        return AdminResponse::json([
            'data' => CreatedPersonalAccessTokenResource::fromResult($created),
        ], 201);
    }

    public function destroy(int $tokenId): JsonResponse
    {
        $user = $this->auth->requireUser();

        if (! $this->revokeOwn->execute($tokenId, $user->userId(), $this->auth->credential())) {
            abort(404);
        }

        return AdminResponse::json([
            'data' => ['revoked' => true],
        ]);
    }
}
