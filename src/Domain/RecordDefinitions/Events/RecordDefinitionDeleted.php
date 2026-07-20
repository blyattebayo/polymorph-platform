<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Events;

final class RecordDefinitionDeleted
{
    public function __construct(
        public readonly int $recordDefinitionId,
    ) {}
}
