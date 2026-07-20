<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Core\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Polymorph\Platform\Domain\Records\Core\Models\Record;
use Polymorph\Platform\Domain\Records\Core\Query\RecordQueryCriteria;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;

interface RecordRepository
{
    /**
     * Отфильтрованные записи определения (where/order/limit поверх jsonb).
     *
     * @return Collection<int, Record>
     */
    public function findForQuery(RecordQueryCriteria $criteria): Collection;

    public function firstForQuery(RecordQueryCriteria $criteria): ?Record;

    public function countForQuery(RecordQueryCriteria $criteria): int;

    public function existsForQuery(RecordQueryCriteria $criteria): bool;

    public function paginateForQuery(RecordQueryCriteria $criteria, int $page, int $perPage): LengthAwarePaginator;

    /**
     * Атомарно увеличивает числовое jsonb-поле записи на delta (jsonb_set).
     * Возвращает true, если строка обновлена (запись существует и не удалена).
     */
    public function incrementField(int $recordId, string $jsonKey, int|float $delta): bool;

    /**
     * Полностью (hard delete) удалить все записи определения, включая
     * мягко удалённые. Используется при force-delete определения.
     *
     * @return int Количество удалённых строк
     */
    public function forceDeleteByDefinition(int $recordDefinitionId): int;

    /**
     * Скалярная агрегация (sum/avg/min/max) числового jsonb-поля по критериям.
     */
    public function aggregate(RecordQueryCriteria $criteria, string $func, string $valueKey): ?float;

    /**
     * Группировка по текстовому значению groupKey с агрегацией count|sum(valueKey).
     *
     * @return array<string, float|int> значение группы → агрегат
     */
    public function groupAggregate(RecordQueryCriteria $criteria, string $func, string $groupKey, ?string $valueKey): array;

    /**
     * Find by ID with recordDefinition eager-loaded.
     */
    public function findWithDefinition(int $id): ?Record;

    /**
     * Find by ID with row-level lock (FOR UPDATE) and recordDefinition eager-loaded.
     */
    public function findWithDefinitionForUpdate(int $id): ?Record;

    /**
     * Find by ID including soft-deleted rows, with recordDefinition eager-loaded.
     */
    public function findTrashed(int $id): ?Record;

    /**
     * Load including soft-deleted rows, with recordDefinition and author.
     * Throws ModelNotFoundException when record does not exist.
     */
    public function findForResponse(int $id): Record;

    /**
     * Load active records by IDs with recordDefinition eager-loaded.
     *
     * @param  int[]  $ids
     * @return Record[]
     */
    public function findByIdsWithDefinition(array $ids): array;

    /**
     * Paginate active records for a definition with response relations.
     */
    public function paginateForDefinition(int $definitionId, PageRequest $pagination): LengthAwarePaginator;

    /**
     * Find a record for read/show including trashed rows, with response relations.
     */
    public function findForRead(int $id): ?Record;

    /**
     * Load active records for hydrate endpoint with recordDefinition eager-loaded.
     *
     * @param  int[]  $ids
     * @return Record[]
     */
    public function findHydratableByIds(array $ids): array;
}
