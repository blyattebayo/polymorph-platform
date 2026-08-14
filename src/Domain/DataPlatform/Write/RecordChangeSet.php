<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Write;

use Polymorph\Platform\Domain\DataPlatform\Projection\ProjectionChangeSet;

final readonly class RecordChangeSet
{
    /**
     * @param  list<string>  $changedFieldIds
     * @param  list<array<string,mixed>>  $events
     */
    public function __construct(
        public array $changedFieldIds,
        public ProjectionChangeSet $projections,
        public array $events,
        public bool $noOp,
    ) {}
}
