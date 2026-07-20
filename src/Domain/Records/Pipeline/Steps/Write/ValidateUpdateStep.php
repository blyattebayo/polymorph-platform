<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Pipeline\Steps\Write;

use Polymorph\Platform\Domain\Records\Pipeline\Contexts\RecordWriteContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

/**
 * Validates UpdateRecordCommand constraints
 */
final class ValidateUpdateStep extends AbstractStep
{
    public function __construct()
    {
        parent::__construct(RecordWriteContext::class);
    }

    public function name(): string
    {
        return 'record.update.validate';
    }

    public function requires(): array
    {
        return [
            'input.pending_payload',
        ];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var RecordWriteContext $context */
        $pendingPayload = $context->pendingPayload();

        if ($pendingPayload === []) {
            return StepResult::failure('Update data cannot be empty');
        }

        return StepResult::success();
    }
}
