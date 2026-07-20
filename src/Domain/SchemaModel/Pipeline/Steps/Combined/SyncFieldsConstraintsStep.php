<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Combined;

use Polymorph\Platform\Domain\SchemaModel\Services\ConstraintManager;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Contexts\SaveSchemaWithFieldsContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

final class SyncFieldsConstraintsStep extends AbstractStep
{
    public function __construct(
        private readonly ConstraintManager $constraintManager,
    ) {
        parent::__construct(SaveSchemaWithFieldsContext::class);
    }

    public function name(): string
    {
        return 'schema-model.combined.sync_fields_constraints';
    }

    public function requires(): array
    {
        return [
            'state.fields_for_constraints_sync',
        ];
    }

    public function shouldRun(PipelineContext $context): bool
    {
        /** @var SaveSchemaWithFieldsContext $context */
        return $context->fieldsForConstraintSync !== [];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var SaveSchemaWithFieldsContext $context */

        foreach ($context->fieldsForConstraintSync as $constraintItem) {
            $this->constraintManager->syncConstraints($constraintItem['field'], $constraintItem['constraints'] ?? []);
        }

        return StepResult::success();
    }
}
