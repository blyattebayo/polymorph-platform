<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

final readonly class ProjectionRebuildBatchResult
{
    /** @param list<int> $changedRecordIds */
    public function __construct(
        public int $processed,
        public array $changedRecordIds,
        public int $lastRecordId,
        public bool $mayHaveMore,
    ) {}
}
