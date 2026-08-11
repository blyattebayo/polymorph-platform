<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\PersonalAccessToken\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\UseCases\ListPersonalAccessTokens;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\UseCases\RevokePersonalAccessToken;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenId;
use Polymorph\Platform\Domain\Auth\Http\PersonalAccessToken\Requests\IndexPersonalAccessTokenRequest;
use Polymorph\Platform\Domain\Auth\Http\PersonalAccessToken\Resources\PersonalAccessTokenResource;
use Polymorph\Platform\Http\Pagination\V2\PaginatedJsonResponse;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;

final readonly class AdministrativePersonalAccessTokenController
{
    public function __construct(
        private ListPersonalAccessTokens $list,
        private RevokePersonalAccessToken $revoke,
        private AuthenticationContext $authentication,
    ) {}

    public function index(IndexPersonalAccessTokenRequest $request): JsonResponse
    {
        $page = $this->list->execute(
            $request->pageRequest(),
            $this->authentication->requireActor(),
        );

        return PaginatedJsonResponse::from($page->mapItems(
            static fn ($token): array => PersonalAccessTokenResource::fromView($token),
        ));
    }

    public function destroy(string $tokenId): JsonResponse
    {
        $this->revoke->revokeAsAdministrator(
            new PersonalAccessTokenId($tokenId),
            $this->authentication->requireActor(),
        );

        return AdminResponse::json(['data' => ['revoked' => true]]);
    }
}
