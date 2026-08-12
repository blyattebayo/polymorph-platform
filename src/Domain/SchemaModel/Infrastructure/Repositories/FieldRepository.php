<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldPath;

/** Concrete persistence operations; all field decisions belong to FieldMutationService. */
final class FieldRepository
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

    /** @param array<string, mixed> $data */
    public function create(array $data): Field
    {
        return Field::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Field $field, array $data): Field
    {
        $field->update($data);

        return $field->fresh() ?? $field;
    }

    public function delete(Field $field): void
    {
        // The parent_id foreign key owns the physical subtree cascade.
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

    /** @return Collection<int, Field> */
    public function getAllDescendants(Field $field): Collection
    {
        return Field::query()
            ->where('schema_id', $field->schema_id)
            ->where('full_path', 'like', $field->full_path.'.%')
            ->orderBy('full_path')
            ->get();
    }
}
