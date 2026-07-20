<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Ownership;

final class MissingResourceOwnershipException extends \LogicException
{
    public static function for(ResourceType $resourceType, int $resourceId): self
    {
        return new self("Missing ownership for {$resourceType->value}:{$resourceId}.");
    }
}
