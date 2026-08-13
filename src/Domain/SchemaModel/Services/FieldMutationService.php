<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Services;

use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\CircularDependencyException;
use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\ConstraintViolationException;
use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\DuplicateFieldPathException;
use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\InvalidParentFieldException;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\Cardinality;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldPath;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\ValidationRules;
use Polymorph\Platform\Domain\SchemaModel\Infrastructure\Repositories\FieldRepository;
use Polymorph\Platform\Domain\SchemaModelValidation\DslValidation\DslValidator;
use Polymorph\Platform\Domain\SchemaModelValidation\Schema\SchemaDescriptorProvider;
use Polymorph\Platform\SharedKernel\SystemFields\SystemFieldNames;
use Polymorph\Platform\Support\Validation\ValidationConstraints;

/** Owns every invariant of the field tree inside one schema transaction. */
final class FieldMutationService
{
    public function __construct(
        private readonly FieldRepository $fields,
        private readonly SchemaDescriptorProvider $descriptors,
        private readonly DslValidator $dslValidator,
    ) {}

    /** @param array<string, mixed> $payload */
    public function create(SchemaModel $schema, array $payload): Field
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '' || SystemFieldNames::isReservedSystemPath($name)) {
            throw ConstraintViolationException::create($name, 'name', 'Field name is empty or reserved.');
        }

        $type = $this->fieldType($payload['type'] ?? null);
        $cardinality = $this->cardinality($payload['cardinality'] ?? null);
        $parent = $this->resolveParent($schema, $this->nullablePositiveId($payload, 'parent_id'));
        $fullPath = $this->pathFor($parent, $name);

        if ($this->fields->pathExists($schema, $fullPath)) {
            throw DuplicateFieldPathException::create($fullPath->toString(), (string) $schema->code);
        }

        $validationRules = $this->validationRules($payload);
        $this->assertValidDsl($schema, $fullPath->toString(), $validationRules);
        $this->assertConstraintsMatchType($type, $payload);
        $this->assertDatabaseCapabilities(
            $type,
            $cardinality,
            $fullPath,
            (bool) ($payload['is_indexed'] ?? false),
            is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
        );

        $field = $this->fields->create([
            'schema_id' => (int) $schema->id,
            'parent_id' => $parent?->id,
            'name' => $name,
            'full_path' => $fullPath->toString(),
            'type' => $type,
            'cardinality' => $cardinality,
            'is_indexed' => (bool) ($payload['is_indexed'] ?? false),
            'is_system' => false,
            'validation_rules' => $validationRules,
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
            'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : null,
        ]);

        if (array_key_exists('constraints', $payload)) {
            $this->syncConstraints($field, is_array($payload['constraints']) ? $payload['constraints'] : []);
        }

        return $field;
    }

    /** @param array<string, mixed> $payload */
    public function update(SchemaModel $schema, array $payload): Field
    {
        $fieldId = (int) ($payload['id'] ?? 0);
        $field = $this->fields->find($fieldId);

        if (! $field instanceof Field || (int) $field->schema_id !== (int) $schema->id) {
            throw ConstraintViolationException::create((string) $fieldId, 'schema', 'Field is not part of the target schema.');
        }
        if ((bool) $field->is_system) {
            throw ConstraintViolationException::create((string) $field->full_path, 'system', 'System fields are immutable.');
        }
        if (array_key_exists('type', $payload) || array_key_exists('cardinality', $payload) || array_key_exists('is_system', $payload)) {
            throw ConstraintViolationException::create((string) $field->full_path, 'immutable', 'type, cardinality and is_system cannot be changed.');
        }

        $update = [];
        $newPath = null;
        $newParent = $field->parent;

        if (array_key_exists('name', $payload) || array_key_exists('parent_id', $payload)) {
            $name = array_key_exists('name', $payload) ? trim((string) $payload['name']) : (string) $field->name;
            if ($name === '' || SystemFieldNames::isReservedSystemPath($name)) {
                throw ConstraintViolationException::create($name, 'name', 'Field name is empty or reserved.');
            }

            $parentId = array_key_exists('parent_id', $payload)
                ? $this->nullablePositiveId($payload, 'parent_id')
                : ($field->parent_id === null ? null : (int) $field->parent_id);
            $newParent = $this->resolveParent($schema, $parentId);

            if ($newParent instanceof Field && (
                $newParent->id === $field->id
                || $newParent->getPathObject()->isChildOf($field->getPathObject())
            )) {
                throw CircularDependencyException::create((int) $field->id, (int) $newParent->id);
            }

            $newPath = $this->pathFor($newParent, $name);
            if ($this->fields->pathExists($schema, $newPath, (int) $field->id)) {
                throw DuplicateFieldPathException::create($newPath->toString(), (string) $schema->code);
            }

            $update['name'] = $name;
            $update['parent_id'] = $newParent?->id;
            $update['full_path'] = $newPath->toString();
        }

        if (array_key_exists('validation_rules', $payload)) {
            $validationRules = $this->validationRules($payload);
            $this->assertValidDsl($schema, $newPath?->toString() ?? (string) $field->full_path, $validationRules);
            $update['validation_rules'] = $validationRules;
        }

        $this->assertDatabaseCapabilities(
            $field->type,
            $field->cardinality,
            $newPath ?? $field->getPathObject(),
            array_key_exists('is_indexed', $payload) ? (bool) $payload['is_indexed'] : (bool) $field->is_indexed,
            array_key_exists('metadata', $payload)
                ? (is_array($payload['metadata']) ? $payload['metadata'] : [])
                : (is_array($field->metadata) ? $field->metadata : []),
        );

        foreach (['is_indexed', 'sort_order', 'metadata'] as $key) {
            if (array_key_exists($key, $payload)) {
                $update[$key] = $payload[$key];
            }
        }

        if ($newPath !== null && $newPath->toString() !== (string) $field->full_path) {
            $this->moveDescendants($field, $newParent);
        }

        $saved = $update === [] ? $field : $this->fields->update($field, $update);

        if (array_key_exists('constraints', $payload)) {
            $this->assertConstraintsMatchType($field->type, $payload);
            $this->syncConstraints($saved, is_array($payload['constraints']) ? $payload['constraints'] : []);
        }

        return $saved;
    }

    /**
     * A selected parent owns deletion of its whole subtree; repeated descendant ids are ignored.
     *
     * @param  list<int>  $fieldIds
     */
    public function deleteMany(SchemaModel $schema, array $fieldIds): void
    {
        if ($fieldIds === []) {
            return;
        }

        $selected = [];
        foreach ($fieldIds as $fieldId) {
            $field = $this->fields->find($fieldId);
            if (! $field instanceof Field || (int) $field->schema_id !== (int) $schema->id) {
                throw ConstraintViolationException::create((string) $fieldId, 'schema', 'Field is not part of the target schema.');
            }
            if ((bool) $field->is_system) {
                throw ConstraintViolationException::create((string) $field->full_path, 'system', 'System fields are immutable.');
            }
            $selected[(int) $field->id] = $field;
        }

        foreach ($selected as $field) {
            $hasSelectedAncestor = false;
            foreach ($selected as $candidate) {
                if ($candidate->id !== $field->id && str_starts_with((string) $field->full_path, (string) $candidate->full_path.'.')) {
                    $hasSelectedAncestor = true;
                    break;
                }
            }
            if (! $hasSelectedAncestor) {
                $this->fields->delete($field);
            }
        }
    }

    private function moveDescendants(Field $field, ?Field $newParent): void
    {
        $oldPath = $field->getPathObject();
        $newPath = $this->pathFor($newParent, (string) $field->name);

        foreach ($this->fields->getAllDescendants($field) as $descendant) {
            $relativeSegments = array_slice(
                $descendant->getPathObject()->segments(),
                count($oldPath->segments()),
            );
            $this->fields->update($descendant, [
                'full_path' => $newPath->toString().'.'.implode('.', $relativeSegments),
            ]);
        }
    }

    private function pathFor(?Field $parent, string $name): FieldPath
    {
        return $parent instanceof Field
            ? FieldPath::fromNameAndParent($name, $parent->getPathObject())
            : FieldPath::fromString($name);
    }

    private function resolveParent(SchemaModel $schema, ?int $parentId): ?Field
    {
        if ($parentId === null) {
            return null;
        }

        $parent = $this->fields->find($parentId);
        if (! $parent instanceof Field) {
            throw InvalidParentFieldException::notFound($parentId);
        }
        if ((int) $parent->schema_id !== (int) $schema->id) {
            throw InvalidParentFieldException::wrongSchema((int) $parent->id, (string) $schema->code, (string) $parent->schema->code);
        }
        if (! $parent->type->isContainer()) {
            throw InvalidParentFieldException::notContainer((int) $parent->id, $parent->type->value);
        }

        return $parent;
    }

    /** @param array<string, mixed> $payload */
    private function nullablePositiveId(array $payload, string $key): ?int
    {
        if (! array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }

        $id = (int) $payload[$key];
        if ($id <= 0) {
            throw ConstraintViolationException::create($key, 'identifier', 'Identifier must be positive.');
        }

        return $id;
    }

    private function fieldType(mixed $value): FieldType
    {
        $type = $value instanceof FieldType ? $value : (is_string($value) ? FieldType::tryFrom($value) : null);

        return $type ?? throw ConstraintViolationException::create('type', 'enum', 'Unsupported field type.');
    }

    private function cardinality(mixed $value): Cardinality
    {
        $cardinality = $value instanceof Cardinality ? $value : (is_string($value) ? Cardinality::tryFrom($value) : null);

        return $cardinality ?? throw ConstraintViolationException::create('cardinality', 'enum', 'Unsupported cardinality.');
    }

    /** @param array<string, mixed> $payload */
    private function validationRules(array $payload): ?ValidationRules
    {
        $rules = $payload['validation_rules'] ?? null;

        return is_array($rules) ? ValidationRules::fromArray($rules) : null;
    }

    private function assertValidDsl(SchemaModel $schema, string $path, ?ValidationRules $rules): void
    {
        $result = $this->dslValidator->validate(
            $rules?->toArray(),
            $this->descriptors->forSchemaId((int) $schema->id),
            $path,
        );

        if ($result->hasErrors()) {
            throw ConstraintViolationException::create($path, 'validation_rules', $result->firstError() ?? 'Invalid validation rules.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function assertConstraintsMatchType(FieldType $type, array $payload): void
    {
        if (! array_key_exists('constraints', $payload) || $payload['constraints'] === null) {
            return;
        }

        $constraints = is_array($payload['constraints']) ? $payload['constraints'] : [];
        $keys = array_keys(array_filter($constraints, static fn (mixed $value): bool => $value !== null && $value !== []));
        $allowed = match ($type) {
            FieldType::REF => ['allowed_record_definition_id'],
            FieldType::MEDIA => ['allowed_mimes'],
            default => [],
        };

        if (array_diff($keys, $allowed) !== []) {
            throw ConstraintViolationException::create(
                $type->value,
                'constraints',
                'Constraint keys do not match the field type.',
            );
        }
    }

    /** @param array<string, mixed> $metadata */
    private function assertDatabaseCapabilities(
        FieldType $type,
        Cardinality $cardinality,
        FieldPath $path,
        bool $isIndexed,
        array $metadata,
    ): void {
        $isUnique = (bool) ($metadata['unique'] ?? false);
        if (! $isIndexed && ! $isUnique) {
            return;
        }

        $capability = $isUnique ? 'unique' : 'is_indexed';
        $reason = match (true) {
            $type->isContainer() => 'JSON container fields do not support scalar database indexes.',
            $cardinality === Cardinality::MANY => 'Many-valued fields require an array-aware index and are not supported yet.',
            ! ValidationConstraints::slug()->matches($path->toString()) => 'Nested field paths do not support dedicated database indexes yet.',
            default => null,
        };

        if ($reason !== null) {
            throw ConstraintViolationException::create($path->toString(), $capability, $reason);
        }
    }

    /** @param array{allowed_record_definition_id?:int|null,allowed_mimes?:string[]|null} $constraints */
    private function syncConstraints(Field $field, array $constraints): void
    {
        if ($field->type === FieldType::REF) {
            $recordDefinitionId = (int) ($constraints['allowed_record_definition_id'] ?? 0);
            if ($recordDefinitionId > 0) {
                $field->refConstraint()->updateOrCreate(
                    ['field_id' => $field->id],
                    ['allowed_record_definition_id' => $recordDefinitionId],
                );
            } else {
                $field->refConstraint()->delete();
            }
        } else {
            $field->refConstraint()->delete();
        }

        $field->mediaConstraints()->delete();
        if ($field->type === FieldType::MEDIA) {
            foreach (($constraints['allowed_mimes'] ?? []) as $mime) {
                $field->mediaConstraints()->create(['allowed_mime' => $mime]);
            }
        }
    }
}
