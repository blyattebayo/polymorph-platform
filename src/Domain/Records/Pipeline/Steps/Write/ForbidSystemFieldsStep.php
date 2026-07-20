<?php

namespace Polymorph\Platform\Domain\Records\Pipeline\Steps\Write;

use Polymorph\Platform\Domain\Records\Support\RecordSystemFields;
use Polymorph\Platform\Domain\Records\Support\RecordPayloadPathRegistry;
use Polymorph\Platform\Domain\Records\Pipeline\Contexts\RecordWriteContext;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

/**
 * Rejects payloads that contain platform-managed system fields.
 */
final class ForbidSystemFieldsStep extends AbstractStep
{
    public function __construct()
    {
        parent::__construct(RecordWriteContext::class);
    }

    public function name(): string
    {
        return 'record.payload.forbid_system_fields';
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

        $errors = [];
        foreach (RecordSystemFields::forbiddenPayloadSystemKeys($pendingPayload) as $key) {
            $errors[RecordPayloadPathRegistry::rootPath($key)] = [
                'System attributes are managed by platform and cannot be provided.',
            ];
        }

        if ($errors !== []) {
            return StepResult::failure(
                error: 'System field validation failed: payload contains platform-managed keys.',
                metadata: [
                    'errors' => $errors,
                ],
            );
        }

        return StepResult::success();
    }
}
