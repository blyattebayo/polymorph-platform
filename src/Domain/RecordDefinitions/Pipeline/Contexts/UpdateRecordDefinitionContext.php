<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Contexts;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\RecordDefinitions\Core\ValueObjects\UpdateRecordDefinitionData;
use Polymorph\Platform\PipelineCore\Locking\LockKey;
use Polymorph\Platform\PipelineCore\Runtime\LockableContext;

final class UpdateRecordDefinitionContext implements LockableContext
{
    public ?RecordDefinition $updatedRecordDefinition = null;

    public function __construct(
        public readonly RecordDefinition $recordDefinition,
        public readonly UpdateRecordDefinitionData $payload,
    ) {}

    public function getLockKey(): LockKey
    {
        return new LockKey(
            resourceType: 'record_definition',
            resourceId: (int) $this->recordDefinition->id,
        );
    }
}
