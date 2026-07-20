<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Core\ValueObjects;

final readonly class ExtensionCapabilityDefinition
{
    public function __construct(
        public string $resource,
        public string $action,
        public string $label,
        public string $scope,
    ) {}
}
