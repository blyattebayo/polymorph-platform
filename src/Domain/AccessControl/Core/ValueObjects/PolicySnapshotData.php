<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\ValueObjects;

final readonly class PolicySnapshotData
{
    public function __construct(
        public int $policyId,
        public string $resourcePattern,
        public string $action,
        public string $effect,
        public int $priority,
    ) {
    }
}