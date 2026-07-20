<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Steps\RecordDefinition;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionDependencyChecker;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Contexts\DeleteRecordDefinitionContext;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;
use Polymorph\Platform\Support\Errors\ErrorCode;

final class ValidateDeleteRecordDefinitionStep extends AbstractStep
{
    public function __construct(
        private readonly RecordDefinitionDependencyChecker $dependencyChecker,
    ) {
        parent::__construct(DeleteRecordDefinitionContext::class);}

    public function name(): string
    {
        return 'post-type.delete.validate';
    }

    public function requires(): array
    {
        return [
            'input.record_definition',
            'input.force',
        ];
    }

    

    

    public function run(PipelineContext $context): StepResult
    {
        /** @var DeleteRecordDefinitionContext $context */
        if ($context->force) {
            return StepResult::success();
        }

        $recordsCount = $this->dependencyChecker->getRecordsCount($context->recordDefinition);
        $constraintsCount = $this->dependencyChecker->getFieldRefConstraintsCount($context->recordDefinition);

        if ($recordsCount > 0 || $constraintsCount > 0) {
            $constraintPaths = $this->dependencyChecker->getFieldRefConstraintPaths($context->recordDefinition);

            $detailParts = [];
            $issues = [
                'reasons' => [],
            ];

            if ($recordsCount > 0) {
                $issues['records_count'] = $recordsCount;
                $issues['reasons'][] = "has {$recordsCount} " . ($recordsCount === 1 ? 'record' : 'records');
                $detailParts[] = "{$recordsCount} " . ($recordsCount === 1 ? 'record' : 'records');
            }

            if ($constraintsCount > 0) {
                $issues['field_ref_constraints_count'] = $constraintsCount;
                $issues['paths'] = $constraintPaths;
                $issues['reasons'][] = "is used in {$constraintsCount} field ref " . ($constraintsCount === 1 ? 'constraint' : 'constraints');
                $detailParts[] = "{$constraintsCount} field ref " . ($constraintsCount === 1 ? 'constraint' : 'constraints');
            }

            $detail = 'Cannot delete record definition while ' . implode(' and ', $detailParts) . ' exist. Use force=1 to cascade delete.';

            return StepResult::failure(
                error: $detail,
                metadata: $issues,
                errorCode: ErrorCode::CONFLICT,
                errorTitle: 'RecordDefinition has dependencies',
            );
        }

        return StepResult::success();
    }
}
