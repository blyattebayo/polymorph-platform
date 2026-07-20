<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Combined;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\FieldWriteService;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Data\CreateFieldData;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Data\UpdateFieldData;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Contexts\SaveSchemaWithFieldsContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

final class PersistFieldsUpsertStep extends AbstractStep
{
    public function __construct(
        private readonly FieldWriteService $fieldWriteService,
    ) {
        parent::__construct(SaveSchemaWithFieldsContext::class);
    }

    public function name(): string
    {
        return 'schema-model.combined.persist_fields_upsert';
    }

    public function requires(): array
    {
        return [
            'state.saved_schema',
            'input.fields_upsert',
        ];
    }

    public function produces(): array
    {
        return [
            'state.fields_for_constraints_sync',
        ];
    }

    public function shouldRun(PipelineContext $context): bool
    {
        /** @var SaveSchemaWithFieldsContext $context */
        return $context->fieldsUpsert !== [];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var SaveSchemaWithFieldsContext $context */

        $schema = $context->schema();
        if ($schema === null) {
            return StepResult::failure('Schema is not available for fields upsert');
        }

        foreach ($context->parsedFieldsUpsert as $upsertItem) {
            $data = $upsertItem['data'];
            $existingField = $upsertItem['field'];

            try {
                if ($data instanceof CreateFieldData) {
                    $field = $this->fieldWriteService->create($schema, $data);
                    $shouldSyncConstraints = $data->constraintsProvided;
                    $constraints = $data->constraints;
                } elseif ($data instanceof UpdateFieldData) {
                    if (!$existingField instanceof Field) {
                        return StepResult::failure(sprintf('Field %d is not found in target schema', $data->fieldId));
                    }

                    $field = $this->fieldWriteService->update($schema, $existingField, $data);
                    $shouldSyncConstraints = $data->constraintsChanged;
                    $constraints = $data->constraints;
                } else {
                    return StepResult::failure('Unsupported upsert payload item type');
                }
            } catch (\DomainException $e) {
                return StepResult::failure($e->getMessage(), cause: $e);
            }

            if ($shouldSyncConstraints) {
                $context->fieldsForConstraintSync[] = [
                    'field' => $field,
                    'constraints' => $constraints,
                ];
            }
        }

        return StepResult::success();
    }
}
