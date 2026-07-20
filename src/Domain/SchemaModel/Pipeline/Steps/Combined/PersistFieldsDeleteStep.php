<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Combined;

use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\FieldRepository;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Contexts\SaveSchemaWithFieldsContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

final class PersistFieldsDeleteStep extends AbstractStep
{
    public function __construct(
        private readonly FieldRepository $fieldRepository,
    ) {
        parent::__construct(SaveSchemaWithFieldsContext::class);
    }

    public function name(): string
    {
        return 'schema-model.combined.persist_fields_delete';
    }

    public function requires(): array
    {
        return [
            'state.saved_schema',
            'input.fields_delete',
        ];
    }

    public function shouldRun(PipelineContext $context): bool
    {
        /** @var SaveSchemaWithFieldsContext $context */
        return $context->fieldsDelete !== [];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var SaveSchemaWithFieldsContext $context */
        $schema = $context->schema();
        if ($schema === null) {
            return StepResult::failure('Schema is not available for fields delete');
        }

        foreach ($context->fieldsDelete as $fieldIdRaw) {
            $field = $this->fieldRepository->find((int) $fieldIdRaw);
            if (! $field instanceof Field) {
                return StepResult::failure(sprintf('Field %d is not found in target schema', (int) $fieldIdRaw));
            }

            $this->fieldRepository->delete($field);
        }

        return StepResult::success();
    }
}
