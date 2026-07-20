<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Contexts;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\RecordDefinitions\Core\ValueObjects\CreateRecordDefinitionData;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;

final class CreateRecordDefinitionContext implements PipelineContext
{
    public ?RecordDefinition $createdRecordDefinition = null;

    public function __construct(
        public readonly CreateRecordDefinitionData $payload,
    ) {}
}
