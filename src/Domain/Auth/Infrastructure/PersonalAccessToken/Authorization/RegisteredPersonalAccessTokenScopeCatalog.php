<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\PersonalAccessToken\Authorization;

use Polymorph\Platform\Domain\AccessControl\Services\CapabilityRegistry;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenScopeCatalog;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenScopes;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final readonly class RegisteredPersonalAccessTokenScopeCatalog implements PersonalAccessTokenScopeCatalog
{
    public function __construct(private CapabilityRegistry $capabilities) {}

    public function unknownScopes(PersonalAccessTokenScopes $scopes): array
    {
        $known = [];

        foreach ($this->capabilities->capabilityDefinitions() as $definition) {
            $known[$definition->key()] = true;
        }

        return array_values(array_filter(
            $scopes->toArray(),
            static fn (array $scope): bool => ! isset($known[
                CapabilityCatalog::capabilityKey($scope['resource'], $scope['action'])
            ]),
        ));
    }
}
