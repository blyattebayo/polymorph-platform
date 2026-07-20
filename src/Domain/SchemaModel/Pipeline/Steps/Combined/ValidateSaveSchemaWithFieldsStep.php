<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Combined;

use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\FieldRepository;
use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\SchemaRepository;
use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\DuplicateSchemaCodeException;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\Cardinality;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Data\CreateFieldData;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Data\UpdateFieldData;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Contexts\SaveSchemaWithFieldsContext;
use Polymorph\Platform\SharedKernel\SystemFields\SystemFieldNames;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;
use Polymorph\Platform\Support\Validation\ValidationConstraints;

final class ValidateSaveSchemaWithFieldsStep extends AbstractStep
{
    public function __construct(
        private readonly SchemaRepository $schemaRepository,
        private readonly FieldRepository $fieldRepository,
    ) {
        parent::__construct(SaveSchemaWithFieldsContext::class);
    }

    public function name(): string
    {
        return 'schema-model.combined.validate';
    }

    public function requires(): array
    {
        return [
            'input.schema_payload',
            'input.fields_upsert',
            'input.fields_delete',
        ];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var SaveSchemaWithFieldsContext $context */

        $schemaValidation = $this->validateSchemaPayload($context);
        if ($schemaValidation !== null) {
            return StepResult::failure($schemaValidation);
        }

        $upsertValidation = $this->validateUpsertItems($context);
        if ($upsertValidation !== null) {
            return StepResult::failure($upsertValidation);
        }

        $deleteValidation = $this->validateDeleteItems($context);
        if ($deleteValidation !== null) {
            return StepResult::failure($deleteValidation);
        }

        return StepResult::success();
    }

    private function validateSchemaPayload(SaveSchemaWithFieldsContext $context): ?string
    {
        $schema = $context->existingSchema;
        $code = (string) ($context->schemaPayload['code'] ?? '');
        $shouldValidateCode = $schema === null || ($code !== '' && $code !== $schema->code);

        if ($shouldValidateCode && !$this->isValidCode($code)) {
            return $this->invalidCodeMessage($code);
        }

        if ($schema === null) {
            if ($code === '') {
                return 'Schema code is required';
            }

            if ($this->schemaRepository->codeExists($code)) {
                return DuplicateSchemaCodeException::create($code)->getMessage();
            }

            return null;
        }

        if ($code !== '' && $code !== $schema->code) {
            if ($this->schemaRepository->codeExists($code, $schema->id)) {
                return DuplicateSchemaCodeException::create($code)->getMessage();
            }
        }

        return null;
    }

    /**
     * Доменная (НЕ только HTTP) проверка грамматики кода схемы. Раньше slug-правило
     * жило лишь в FormRequest, поэтому внутренние вызовы сервиса (плагинный SDK,
     * сидеры) могли записать невалидный код в обход контракта. Сервис — источник
     * правды, поэтому валидируем здесь, на любом входе в pipeline.
     */
    private function isValidCode(string $code): bool
    {
        return ValidationConstraints::slug()->matches($code);
    }

    private function invalidCodeMessage(string $code): string
    {
        return sprintf(
            "Schema code '%s' must match slug grammar (%s)",
            $code,
            ValidationConstraints::slug()->pattern(),
        );
    }

    private function validateUpsertItems(SaveSchemaWithFieldsContext $context): ?string
    {
        $schema = $context->existingSchema;
        $seenIds = [];
        $context->parsedFieldsUpsert = [];

        foreach ($context->fieldsUpsert as $index => $item) {
            if (!is_array($item)) {
                return sprintf('fields.upsert[%d] must be an object', $index);
            }

            if (!array_key_exists('id', $item)) {
                $createValidation = $this->validateCreateItem($item, $index);
                if ($createValidation !== null) {
                    return $createValidation;
                }

                $createData = CreateFieldData::fromArray($item);
                $context->parsedFieldsUpsert[] = [
                    'data' => $createData,
                    'field' => null,
                ];

                continue;
            }

            if ($schema === null) {
                return sprintf('fields.upsert[%d].id is not allowed when schema is being created', $index);
            }

            $fieldId = (int) $item['id'];
            if ($fieldId <= 0) {
                return sprintf('fields.upsert[%d].id must be a positive integer', $index);
            }

            if (isset($seenIds[$fieldId])) {
                return sprintf('fields.upsert contains duplicate field id: %d', $fieldId);
            }
            $seenIds[$fieldId] = true;

            $field = $this->fieldRepository->find($fieldId);
            if (!$field instanceof Field || (int) $field->schema_id !== (int) $schema->id) {
                return sprintf('Field %d is not found in target schema', $fieldId);
            }

            if ((bool) $field->is_system) {
                return sprintf('System field %d is immutable and cannot be updated', $fieldId);
            }

            $updateValidation = $this->validateUpdateItem($item, $index);
            if ($updateValidation !== null) {
                return $updateValidation;
            }

            $updateData = UpdateFieldData::fromArray($item);
            $context->parsedFieldsUpsert[] = [
                'data' => $updateData,
                'field' => $field,
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function validateCreateItem(array $item, int $index): ?string
    {
        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            return sprintf('fields.upsert[%d].name is required', $index);
        }

        if (SystemFieldNames::isReservedSystemPath($name)) {
            return sprintf('fields.upsert[%d].name uses reserved system namespace', $index);
        }

        if ((bool) ($item['is_system'] ?? false)) {
            return sprintf('fields.upsert[%d].is_system is prohibited', $index);
        }

        $type = $item['type'] ?? null;
        if (!$type instanceof FieldType && (!is_string($type) || FieldType::tryFrom($type) === null)) {
            return sprintf('fields.upsert[%d].type is invalid', $index);
        }

        $cardinality = $item['cardinality'] ?? null;
        if (!$cardinality instanceof Cardinality && (!is_string($cardinality) || Cardinality::tryFrom($cardinality) === null)) {
            return sprintf('fields.upsert[%d].cardinality is invalid', $index);
        }

        if (array_key_exists('parent_id', $item) && $item['parent_id'] !== null && (int) $item['parent_id'] <= 0) {
            return sprintf('fields.upsert[%d].parent_id must be a positive integer', $index);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function validateUpdateItem(array $item, int $index): ?string
    {
        if (array_key_exists('type', $item) || array_key_exists('cardinality', $item)) {
            return sprintf('fields.upsert[%d] cannot change type/cardinality for existing field', $index);
        }

        if (array_key_exists('name', $item)) {
            $name = trim((string) $item['name']);
            if ($name === '') {
                return sprintf('fields.upsert[%d].name cannot be empty', $index);
            }

            if (SystemFieldNames::isReservedSystemPath($name)) {
                return sprintf('fields.upsert[%d].name uses reserved system namespace', $index);
            }
        }

        if (array_key_exists('parent_id', $item) && $item['parent_id'] !== null && (int) $item['parent_id'] <= 0) {
            return sprintf('fields.upsert[%d].parent_id must be a positive integer', $index);
        }

        return null;
    }

    private function validateDeleteItems(SaveSchemaWithFieldsContext $context): ?string
    {
        $schema = $context->existingSchema;
        if ($schema === null && $context->fieldsDelete !== []) {
            return 'fields.delete is not allowed when schema is being created';
        }

        if ($schema === null) {
            return null;
        }

        $seenIds = [];
        foreach ($context->fieldsDelete as $index => $fieldIdRaw) {
            $fieldId = (int) $fieldIdRaw;
            if ($fieldId <= 0) {
                return sprintf('fields.delete[%d] must be a positive integer', $index);
            }

            if (isset($seenIds[$fieldId])) {
                return sprintf('fields.delete contains duplicate field id: %d', $fieldId);
            }
            $seenIds[$fieldId] = true;

            $field = $this->fieldRepository->find($fieldId);
            if (!$field instanceof Field || (int) $field->schema_id !== (int) $schema->id) {
                return sprintf('Field %d is not found in target schema', $fieldId);
            }

            if ((bool) $field->is_system) {
                return sprintf('System field %d is immutable and cannot be deleted', $fieldId);
            }
        }

        return null;
    }
}
