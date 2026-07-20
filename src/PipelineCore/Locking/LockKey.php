<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Locking;

readonly class LockKey
{
    public function __construct(
        public string $resourceType,
        public int|string $resourceId,
        public ?string $scope = null
    ) {}
    
    public function toString(): string
    {
        $base = "{$this->resourceType}:{$this->resourceId}";
        return $this->scope ? "{$base}:{$this->scope}" : $base;
    }
}
