<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Contexts;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\PipelineCore\Locking\LockKey;
use Polymorph\Platform\PipelineCore\Runtime\LockableContext;

final class DeleteRecordDefinitionContext implements LockableContext
{
    public function __construct(
        public readonly RecordDefinition $recordDefinition,
        public readonly bool $force,
    ) {}

    public function getLockKey(): LockKey
    {
        return new LockKey(
            resourceType: 'record_definition',
            resourceId: (int) $this->recordDefinition->id,
        );
    }
}
