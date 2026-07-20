<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Pipeline\Steps\Write;

use Polymorph\Platform\Domain\Records\Pipeline\Contexts\RecordWriteContext;
use Polymorph\Platform\Domain\Records\Support\RecordDataNormalizer;
use Polymorph\Platform\Domain\Records\Support\RecordSchemaValueNormalizer;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

/**
 * Нормализует schema-значения после валидации и до записи в data_json.
 */
final class NormalizeSchemaDataStep extends AbstractStep
{
    public function __construct(
        private readonly RecordSchemaValueNormalizer $normalizer,
    ) {
        parent::__construct(RecordWriteContext::class);
    }

    public function name(): string
    {
        return 'record.normalize_schema_data';
    }

    public function requires(): array
    {
        return [
            'input.pending_payload',
            'input.record_definition',
        ];
    }

    public function produces(): array
    {
        return [
            'input.normalized_payload',
        ];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var RecordWriteContext $context */
        $recordDefinition = $context->requireRecordDefinition();
        $schemaId = $recordDefinition->schema_id;

        if ($schemaId === null) {
            $context->setNormalizedPayload(RecordDataNormalizer::fromMixed($context->pendingPayload()));

            return StepResult::success();
        }

        try {
            $normalized = $this->normalizer->normalizeForSchema(
                (int) $schemaId,
                RecordDataNormalizer::fromMixed($context->pendingPayload()),
            );
        } catch (\InvalidArgumentException $exception) {
            return StepResult::failure(
                error: 'Schema normalization failed: '.$exception->getMessage(),
            );
        }

        $context->setNormalizedPayload($normalized);

        return StepResult::success();
    }
}
