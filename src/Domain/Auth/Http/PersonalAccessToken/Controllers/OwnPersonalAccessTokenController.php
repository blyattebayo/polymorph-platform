<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\PersonalAccessToken\Controllers;

use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\UseCases\IssuePersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\UseCases\ListOwnPersonalAccessTokens;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\UseCases\RevokePersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenId;
use Polymorph\Platform\Domain\Auth\Http\PersonalAccessToken\Requests\IssuePersonalAccessTokenRequest;
use Polymorph\Platform\Domain\Auth\Http\PersonalAccessToken\Resources\IssuedPersonalAccessTokenResource;
use Polymorph\Platform\Domain\Auth\Http\PersonalAccessToken\Resources\PersonalAccessTokenResource;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;

final readonly class OwnPersonalAccessTokenController
{
    public function __construct(
        private ListOwnPersonalAccessTokens $list,
        private IssuePersonalAccessToken $issue,
        private RevokePersonalAccessToken $revoke,
        private AuthenticationContext $authentication,
    ) {}

    public function index(): JsonResponse
    {
        return AdminResponse::json([
            'data' => PersonalAccessTokenResource::collection(
                $this->list->execute($this->authentication->requireActor()),
            ),
        ]);
    }

    public function store(IssuePersonalAccessTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $issued = $this->issue->execute(
            (string) $validated['name'],
            new DateTimeImmutable((string) $validated['expires_at']),
            (array) $validated['scopes'],
            $this->authentication->requireActor(),
        );

        return AdminResponse::json([
            'data' => IssuedPersonalAccessTokenResource::fromResult($issued),
        ], 201);
    }

    public function destroy(string $tokenId): JsonResponse
    {
        $this->revoke->revokeOwn(
            new PersonalAccessTokenId($tokenId),
            $this->authentication->requireActor(),
        );

        return AdminResponse::json(['data' => ['revoked' => true]]);
    }
}
