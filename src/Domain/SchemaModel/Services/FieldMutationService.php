<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Services;

use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\FieldRepository;
use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\FieldWriteService;
use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\CircularDependencyException;
use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\DuplicateFieldPathException;
use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\InvalidParentFieldException;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Data\CreateFieldData;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Data\UpdateFieldData;
use Polymorph\Platform\Domain\SchemaModelValidation\Contracts\SchemaDescriptorProviderInterface;
use Polymorph\Platform\Domain\SchemaModelValidation\DslValidation\DslValidator;

final class FieldMutationService implements FieldWriteService
{
    public function __construct(
        private readonly FieldRepository $fieldRepository,
        private readonly FieldPathCalculator $pathCalculator,
        private readonly SchemaDescriptorProviderInterface $schemaDescriptorProvider,
        private readonly DslValidator $dslValidator,
    ) {}

    /**
     * @throws DuplicateFieldPathException путь уже занят в схеме (409)
     * @throws InvalidParentFieldException родитель не найден/чужой/не контейнер
     * @throws \DomainException DSL валидации полей не прошёл
     */
    public function create(SchemaModel $schema, CreateFieldData $data): Field
    {
        $parent = $this->resolveParentForCreate($schema, $data->parentId);

        $fullPath = $this->pathCalculator->calculatePath($parent, $data->name);
        if ($this->fieldRepository->pathExists($schema, $fullPath)) {
            throw DuplicateFieldPathException::create($fullPath->toString(), (string) $schema->code);
        }

        $dslResult = $this->dslValidator->validate(
            $data->validationRules?->toArray(),
            $this->schemaDescriptorProvider->forSchemaId((int) $schema->id),
            $fullPath->toString(),
        );
        if ($dslResult->hasErrors()) {
            throw new \DomainException(
                'Field DSL validation failed: '.($dslResult->firstError() ?? 'Invalid validation_rules.'),
            );
        }

        $payload = [
            'schema_id' => (int) $schema->id,
            'parent_id' => $parent?->id,
            'name' => $data->name,
            'full_path' => $fullPath->toString(),
            'type' => $data->type,
            'cardinality' => $data->cardinality,
            'is_indexed' => $data->isIndexed,
            'is_system' => false,
            'sort_order' => $data->sortOrder,
            'metadata' => $data->metadata,
        ];

        if ($data->validationRules !== null) {
            $payload['validation_rules'] = $data->validationRules;
        }

        return $this->fieldRepository->create($payload);
    }

    /**
     * @throws DuplicateFieldPathException новый путь уже занят в схеме (409)
     * @throws CircularDependencyException смена родителя создала бы цикл
     * @throws InvalidParentFieldException родитель не найден/чужой/не контейнер
     * @throws \DomainException DSL валидации полей не прошёл
     */
    public function update(SchemaModel $schema, Field $field, UpdateFieldData $data): Field
    {
        $updateData = [];
        $newParent = $field->parent;
        $newPath = null;

        if ($data->name !== null || $data->parentChange->changed) {
            $newName = $data->name ?? (string) $field->name;
            $newParentId = $data->parentChange->changed ? $data->parentChange->targetParentId : $field->parent_id;

            $newParent = $this->resolveParentForUpdate($schema, $field, $newParentId);
            $newPath = $this->pathCalculator->calculatePath($newParent, $newName);
            if ($this->fieldRepository->pathExists($schema, $newPath, (int) $field->id)) {
                throw DuplicateFieldPathException::create($newPath->toString(), (string) $schema->code);
            }

            $updateData['name'] = $newName;
            $updateData['parent_id'] = $newParent?->id;
            $updateData['full_path'] = $newPath->toString();
        }

        $pathChanges = null;
        $descendantsById = [];
        if ($newPath !== null) {
            $pathChanges = $this->pathCalculator->recalculatePathsOnParentChange($field, $newParent);
            $descendants = $this->fieldRepository->getAllDescendants($field);

            foreach ($descendants as $descendant) {
                $descendantsById[(int) $descendant->id] = $descendant;
            }
        }

        if ($data->validationRulesChanged) {
            $dslPath = $newPath?->toString() ?? (string) $field->full_path;
            $dslResult = $this->dslValidator->validate(
                $data->validationRules?->toArray(),
                $this->schemaDescriptorProvider->forSchemaId((int) $schema->id),
                $dslPath,
            );
            if ($dslResult->hasErrors()) {
                throw new \DomainException(
                    'Field DSL validation failed: '.($dslResult->firstError() ?? 'Invalid validation_rules.'),
                );
            }

            $updateData['validation_rules'] = $data->validationRules;
        }

        if ($data->isIndexedChanged) {
            $updateData['is_indexed'] = $data->isIndexed;
        }

        if ($data->sortOrderChanged) {
            $updateData['sort_order'] = $data->sortOrder;
        }

        if ($data->metadataChanged) {
            $updateData['metadata'] = $data->metadata;
        }

        if ($newPath !== null && $newPath->toString() !== (string) $field->full_path && is_array($pathChanges)) {
            foreach ($pathChanges['descendants'] as $descendantId => $descendantPath) {
                $descendant = $descendantsById[(int) $descendantId] ?? null;
                if (! $descendant instanceof Field) {
                    continue;
                }

                $this->fieldRepository->update($descendant, [
                    'full_path' => $descendantPath->toString(),
                ]);
            }
        }

        if ($updateData === []) {
            return $field;
        }

        return $this->fieldRepository->update($field, $updateData);
    }

    /**
     * @throws InvalidParentFieldException
     */
    private function resolveParentForCreate(SchemaModel $schema, ?int $parentId): ?Field
    {
        if ($parentId === null) {
            return null;
        }

        return $this->resolveParentById($schema, $parentId);
    }

    /**
     * @throws CircularDependencyException
     * @throws InvalidParentFieldException
     */
    private function resolveParentForUpdate(SchemaModel $schema, Field $field, ?int $parentId): ?Field
    {
        if ($parentId === null) {
            return null;
        }

        $parent = $this->resolveParentById($schema, $parentId);
        if ($this->pathCalculator->wouldCreateCircularDependency($field, $parent)) {
            throw CircularDependencyException::create((int) $field->id, (int) $parent->id);
        }

        return $parent;
    }

    /**
     * @throws InvalidParentFieldException
     */
    private function resolveParentById(SchemaModel $schema, int $parentId): Field
    {
        $parent = $this->fieldRepository->find($parentId);
        if (! $parent instanceof Field) {
            throw InvalidParentFieldException::notFound($parentId);
        }

        if ((int) $parent->schema_id !== (int) $schema->id) {
            throw InvalidParentFieldException::wrongSchema(
                (int) $parent->id,
                (string) $schema->code,
                (string) $parent->schema->code,
            );
        }

        if (! $parent->type->isContainer()) {
            throw InvalidParentFieldException::notContainer((int) $parent->id, $parent->type->value);
        }

        return $parent;
    }
}
