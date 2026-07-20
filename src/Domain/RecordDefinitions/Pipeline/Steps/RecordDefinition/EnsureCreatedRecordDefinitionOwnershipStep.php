<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Steps\RecordDefinition;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Contexts\CreateRecordDefinitionContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwnershipService;
use Polymorph\Platform\SharedKernel\Ownership\ResourceType;

final class EnsureCreatedRecordDefinitionOwnershipStep extends AbstractStep
{
    public function __construct(
        private readonly ResourceOwnershipService $ownershipService,
    ) {
        parent::__construct(CreateRecordDefinitionContext::class);
    }

    public function name(): string
    {
        return 'record_definition.create.ensure_ownership';
    }

    public function requires(): array
    {
        return [
            'state.created_record_definition',
        ];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var CreateRecordDefinitionContext $context */
        if (!$context->createdRecordDefinition instanceof RecordDefinition) {
            throw new \RuntimeException('RecordDefinition ownership step requires created record definition.');
        }

        $schemaId = $context->createdRecordDefinition->schema_id;
        if ($schemaId === null) {
            $this->ownershipService->ensurePlatformOwner(
                ResourceType::RECORD_DEFINITION,
                (int) $context->createdRecordDefinition->id,
            );

            return StepResult::success();
        }

        $this->ownershipService->copy(
            ResourceType::SCHEMA,
            (int) $schemaId,
            ResourceType::RECORD_DEFINITION,
            (int) $context->createdRecordDefinition->id,
        );

        return StepResult::success();
    }
}
