<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\OAuth\Data;

final readonly class AuthorizationRequest
{
    public function __construct(
        public OAuthClient $client,
        public string $redirectUri,
        public string $resource,
        public string $scope,
        public string $codeChallenge,
        public ?string $state,
    ) {}
}
