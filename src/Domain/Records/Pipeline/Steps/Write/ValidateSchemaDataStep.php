<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Pipeline\Steps\Write;

use Illuminate\Support\Facades\Validator;
use Polymorph\Platform\Domain\Records\Pipeline\Contexts\RecordWriteContext;
use Polymorph\Platform\Domain\Records\Support\RecordPayloadPathRegistry;
use Polymorph\Platform\Domain\SchemaModelValidation\Contracts\SchemaDescriptorProviderInterface;
use Polymorph\Platform\Domain\SchemaModelValidation\Contracts\SchemaValidationRulesEngineInterface;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

/**
 * Enforces schema-driven validation inside write pipeline.
 *
 * Canonical domain-level payload validation lives here (not in transport FormRequest).
 * This guarantees consistent validation for HTTP, CLI/jobs and direct service calls.
 */
final class ValidateSchemaDataStep extends AbstractStep
{
    public function __construct(
        private readonly SchemaDescriptorProviderInterface $schemaDescriptorProvider,
        private readonly SchemaValidationRulesEngineInterface $validationEngine,
    ) {
        parent::__construct(RecordWriteContext::class);
    }

    public function name(): string
    {
        return 'record.validate_schema_data';
    }

    public function requires(): array
    {
        return [
            'input.pending_payload',
            'input.record_definition',
        ];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var RecordWriteContext $context */
        $rules = $this->resolveRules($context);
        if ($rules === []) {
            return StepResult::success();
        }

        $dataJson = $context->pendingPayload();

        $validator = Validator::make([
            RecordPayloadPathRegistry::ROOT_KEY => $dataJson,
        ], $rules);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $firstError = $validator->errors()->first();

            return StepResult::failure(
                error: sprintf('Schema validation failed: %s', $firstError),
                metadata: [
                    'errors' => $errors,
                ],
            );
        }

        return StepResult::success();
    }

    /**
     * @return array<string, array<int, string|object>>
     */
    private function resolveRules(RecordWriteContext $context): array
    {
        $recordDefinition = $context->requireRecordDefinition();
        $schemaId = $recordDefinition->schema_id;

        if ($schemaId === null) {
            return [];
        }

        return $this->validationEngine->buildLaravelRulesForDescriptor(
            $this->schemaDescriptorProvider->forSchemaId($schemaId),
        );
    }
}
