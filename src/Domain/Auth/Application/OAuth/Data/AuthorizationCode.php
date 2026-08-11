<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\OAuth\Data;

final readonly class AuthorizationCode
{
    public function __construct(
        public string $clientId,
        public int $userId,
        public string $redirectUri,
        public string $resource,
        public string $scope,
        public string $codeChallenge,
    ) {}
}
