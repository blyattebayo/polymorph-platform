<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Contracts;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldPath;
use Polymorph\Platform\Domain\SchemaModel\Core\Collections\FieldCollection;

/**
 * Репозиторий для работы с полями.
 */
interface FieldRepository
{
    /**
     * Найти поле по ID.
     */
    public function find(int|string $id): ?Field;

    /**
     * Найти поле по пути в схеме.
     */
    public function findByPath(SchemaModel $schema, FieldPath $path): ?Field;

    /**
     * Создать новое поле.
     * 
     * @param array{
     *   schema_id: int,
     *   parent_id?: int|null,
     *   name: string,
     *   full_path: string,
     *   type: string,
     *   cardinality?: string,
     *   is_indexed?: bool,
    *   is_system?: bool,
     *   validation_rules?: array|null,
     *   sort_order?: int,
     *   metadata?: array|null
     * } $data
     */
    public function create(array $data): Field;

    /**
     * Обновить поле.
     */
    public function update(Field $field, array $data): Field;

    /**
     * Удалить поле.
     */
    public function delete(Field $field): void;

    /**
     * Проверить существование пути.
     */
    public function pathExists(SchemaModel $schema, FieldPath $path, ?int $exceptId = null): bool;

    /**
     * Получить все дочерние поля рекурсивно.
     */
    public function getAllDescendants(Field $field): FieldCollection;

}
