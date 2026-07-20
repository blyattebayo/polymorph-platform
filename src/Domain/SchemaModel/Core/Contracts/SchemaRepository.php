<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\SchemaUsageInfo;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;

/**
 * Репозиторий для работы со схемами.
 *
 * Абстракция над БД для тестируемости и гибкости.
 */
interface SchemaRepository
{
    /**
     * Найти схему по ID.
     */
    public function find(int $id): ?SchemaModel;

    /**
     * Создать новую схему.
     *
     * @param  array{name: string, code: string, description?: string|null, metadata?: array|null}  $data
     */
    public function create(array $data): SchemaModel;

    /**
     * Обновить схему.
     *
     * @param  array{name?: string, code?: string, description?: string|null, metadata?: array|null}  $data
     */
    public function update(SchemaModel $schema, array $data): SchemaModel;

    /**
     * Удалить схему.
     */
    public function delete(SchemaModel $schema): void;

    /**
     * Получить информацию об использовании схемы в RecordDefinition.
     *
     * Единый источник правды: count, факт использования и список связанных
     * RecordDefinition собираются одним запросом.
     */
    public function getUsageInfo(SchemaModel $schema): SchemaUsageInfo;

    /**
     * Проверить существование кода.
     */
    public function codeExists(string $code, ?int $exceptId = null): bool;

    /**
     * Поиск схем.
     *
     * @param array{
     *   search?: string,
     *   sort_by?: string,
     *   sort_dir?: string,
     *   in_use?: bool
     * } $criteria
     */
    public function search(array $criteria, PageRequest $pagination): LengthAwarePaginator;
}
