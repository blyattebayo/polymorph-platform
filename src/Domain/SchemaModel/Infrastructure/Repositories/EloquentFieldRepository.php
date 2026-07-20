<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Infrastructure\Repositories;

use Polymorph\Platform\Domain\SchemaModel\Core\Collections\FieldCollection;
use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\FieldRepository;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldPath;

/**
 * Eloquent реализация FieldRepository.
 */
class EloquentFieldRepository implements FieldRepository
{
    public function find(int|string $id): ?Field
    {
        return Field::find((int) $id);
    }

    public function findByPath(SchemaModel $schema, FieldPath $path): ?Field
    {
        return Field::query()
            ->where('schema_id', $schema->id)
            ->where('full_path', $path->toString())
            ->first();
    }

    public function create(array $data): Field
    {
        // Calculate full_path if not provided
        if (! isset($data['full_path'])) {
            if (isset($data['parent_id']) && $data['parent_id'] !== null) {
                $parent = $this->find($data['parent_id']);
                $data['full_path'] = $parent->full_path.'.'.$data['name'];
            } else {
                $data['full_path'] = $data['name'];
            }
        }

        return Field::create($data);
    }

    public function update(Field $field, array $data): Field
    {
        $field->update($data);

        return $field->fresh();
    }

    public function delete(Field $field): void
    {
        // Удалить все дочерние поля рекурсивно
        $descendants = $this->getAllDescendants($field);
        foreach ($descendants as $descendant) {
            $descendant->delete();
        }

        $field->delete();
    }

    public function pathExists(SchemaModel $schema, FieldPath $path, ?int $exceptId = null): bool
    {
        $query = Field::query()
            ->where('schema_id', $schema->id)
            ->where('full_path', $path->toString());

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function getAllDescendants(Field $field): FieldCollection
    {
        $path = $field->full_path;

        return Field::query()
            ->where('schema_id', $field->schema_id)
            ->where('full_path', 'like', "{$path}.%")
            ->orderBy('full_path')
            ->get();
    }
}
