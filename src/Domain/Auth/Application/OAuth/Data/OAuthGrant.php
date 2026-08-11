<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\OAuth\Data;

final readonly class OAuthGrant
{
    public function __construct(
        public string $id,
        public string $clientId,
        public int $userId,
        public string $resource,
        public string $scope,
    ) {}
}
