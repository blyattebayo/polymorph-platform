<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Auth\Application\DTO\CreatePersonalAccessTokenCommand;
use Polymorph\Platform\Domain\Auth\Application\UseCases\CreatePersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Application\UseCases\ListPersonalAccessTokensForUser;
use Polymorph\Platform\Domain\Auth\Application\UseCases\RevokePersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Http\Requests\StorePersonalAccessTokenRequest;
use Polymorph\Platform\Domain\Auth\Http\Resources\CreatedPersonalAccessTokenResource;
use Polymorph\Platform\Domain\Auth\Http\Resources\PersonalAccessTokenResource;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;

/**
 * Свои токены. «Свой» здесь — не отдельный use-case, а подставленный userId
 * текущего актора: сами операции общие с админским контроллером.
 */
final class MePersonalAccessTokenController
{
    public function __construct(
        private readonly ListPersonalAccessTokensForUser $list,
        private readonly CreatePersonalAccessToken $create,
        private readonly RevokePersonalAccessToken $revoke,
        private readonly AuthenticationContext $auth,
    ) {}

    public function index(): JsonResponse
    {
        $user = $this->auth->requireUser();

        return AdminResponse::json([
            'data' => PersonalAccessTokenResource::collection(
                $this->list->execute($user->userId()),
            ),
        ]);
    }

    public function store(StorePersonalAccessTokenRequest $request): JsonResponse
    {
        $user = $this->auth->requireUser();
        $validated = $request->validated();

        $created = $this->create->execute(
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

        $revoked = $this->revoke->execute(
            $tokenId,
            restrictToUserId: $user->userId(),
            actorCredential: $this->auth->credential(),
        );

        if (! $revoked) {
            abort(404);
        }

        return AdminResponse::json([
            'data' => ['revoked' => true],
        ]);
    }
}
