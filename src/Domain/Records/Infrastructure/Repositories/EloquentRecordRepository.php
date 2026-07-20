<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Infrastructure\Repositories;

use Polymorph\Platform\Domain\Records\Core\Contracts\RecordRepository;
use Polymorph\Platform\Domain\Records\Core\Models\Record;
use Polymorph\Platform\Domain\Records\Core\Query\RecordQueryCriteria;
use Polymorph\Platform\Domain\Records\Core\Query\RecordQueryStrategy;
use Polymorph\Platform\Domain\Records\Query\RecordFieldSqlExpression;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentRecordRepository implements RecordRepository
{
    public function incrementField(int $recordId, string $jsonKey, int|float $delta): bool
    {
        // Атомарно: COALESCE текущего значения (или 0) + delta, обратно в jsonb.
        $affected = DB::update(
            'UPDATE records SET data_json = jsonb_set(data_json, array[?], '
            . 'to_jsonb(COALESCE((data_json ->> ?)::numeric, 0) + ?), true), '
            . 'revision = revision + 1, updated_at = now() '
            . 'WHERE id = ? AND deleted_at IS NULL',
            [$jsonKey, $jsonKey, $delta, $recordId],
        );

        return $affected > 0;
    }

    public function findForQuery(RecordQueryCriteria $criteria): Collection
    {
        $query = $this->applyCriteria($criteria);

        if ($criteria->limit !== null) {
            $query->limit($criteria->limit);
        }

        return $query->get();
    }

    public function firstForQuery(RecordQueryCriteria $criteria): ?Record
    {
        return $this->applyCriteria($criteria)->first();
    }

    public function countForQuery(RecordQueryCriteria $criteria): int
    {
        return $this->applyCriteria($criteria, withOrder: false)->count();
    }

    public function existsForQuery(RecordQueryCriteria $criteria): bool
    {
        return $this->applyCriteria($criteria, withOrder: false)->exists();
    }

    public function paginateForQuery(RecordQueryCriteria $criteria, int $page, int $perPage): LengthAwarePaginator
    {
        return $this->applyCriteria($criteria)->paginate(perPage: $perPage, page: $page);
    }

    public function aggregate(RecordQueryCriteria $criteria, string $func, string $valueKey): ?float
    {
        // func — из белого списка (SdkRecordStore), валидно для интерполяции.
        $expr = RecordFieldSqlExpression::safeNumericExpression($valueKey);
        $value = $this->applyCriteria($criteria, withOrder: false)
            ->selectRaw("{$func}({$expr}) as agg")
            ->value('agg');

        return $value === null ? null : (float) $value;
    }

    public function groupAggregate(RecordQueryCriteria $criteria, string $func, string $groupKey, ?string $valueKey): array
    {
        // Ключи инлайним валидированными литералами: select и group by должны быть
        // текстуально идентичны (Postgres не матчит (data_json->>$1) и (data_json->>$3)).
        $group = RecordFieldSqlExpression::safeJsonKey($groupKey);
        $groupExpr = RecordFieldSqlExpression::fieldExpression($group, null);

        $query = $this->applyCriteria($criteria, withOrder: false)->setEagerLoads([]);

        if ($func === 'count') {
            $query->selectRaw("{$groupExpr} as grp, count(*) as agg");
        } else {
            $value = RecordFieldSqlExpression::safeJsonKey((string) $valueKey);
            $valueExpr = RecordFieldSqlExpression::safeNumericExpression($value);
            $query->selectRaw("{$groupExpr} as grp, sum({$valueExpr}) as agg");
        }

        $rows = $query->groupByRaw($groupExpr)->get();

        $result = [];
        foreach ($rows as $row) {
            $key = $row->grp === null ? '' : (string) $row->grp;
            $result[$key] = $func === 'count' ? (int) $row->agg : (float) $row->agg;
        }

        return $result;
    }

    /**
     * Собирает Eloquent-запрос из критериев: scope по определению/автору + условия
     * и сортировки по полям jsonb (data_json->>key с типизированным кастом). Ключи
     * инлайнятся безопасными литералами, чтобы Postgres матчила expression-индексы;
     * значения остаются bind-параметрами. Касты/операторы — из белых списков.
     */
    private function applyCriteria(RecordQueryCriteria $criteria, bool $withOrder = true): Builder
    {
        // definitionId инлайнится литералом (доверенный int): предикат запроса должен
        // текстуально совпасть с partial-предикатом индекса (record_definition_id = <const>),
        // иначе под generic-планом частичный индекс не применяется.
        $query = Record::query()
            ->with(['recordDefinition', 'author'])
            ->whereRaw('record_definition_id = ' . (int) $criteria->definitionId)
            ->whereNull('deleted_at');

        if ($criteria->authorId !== null) {
            $query->where('author_id', $criteria->authorId);
        }

        foreach ($criteria->conditions as $condition) {
            $key = RecordFieldSqlExpression::safeJsonKey($condition->key);

            if ($condition->strategy === RecordQueryStrategy::Containment && $condition->op === '=') {
                $fragment = json_encode([$key => $condition->value], JSON_THROW_ON_ERROR);
                $query->whereRaw('data_json @> ?::jsonb', [$fragment]);
                continue;
            }

            $expr = RecordFieldSqlExpression::fieldExpression($key, $condition->cast);

            if ($condition->op === 'notnull') {
                $query->whereRaw("{$expr} is not null");
                continue;
            }

            if ($condition->op === 'isnull') {
                $query->whereRaw("{$expr} is null");
                continue;
            }

            if ($condition->op === 'in') {
                $values = is_array($condition->value) ? array_values($condition->value) : [$condition->value];
                if ($values === []) {
                    $query->whereRaw('1 = 0');
                    continue;
                }
                $placeholders = implode(', ', array_fill(0, count($values), '?'));
                $query->whereRaw("{$expr} in ({$placeholders})", $values);
                continue;
            }

            $query->whereRaw("{$expr} {$condition->op} ?", [$condition->value]);
        }

        if ($withOrder) {
            // Детерминированный tie-break по системному id (монотонный автоинкремент).
            // Направление совпадает с направлением ПОСЛЕДНЕГО заданного порядка, чтобы
            // расширять сортировку согласованно: при ASC-порядке ничьи разрешаются
            // id ASC (хронология не переворачивается), при DESC/без порядка — id DESC.
            $tieDir = 'desc';
            foreach ($criteria->orders as $order) {
                $key = RecordFieldSqlExpression::safeJsonKey($order['key']);
                $expr = RecordFieldSqlExpression::fieldExpression($key, $order['cast']);
                $query->orderByRaw("{$expr} {$order['dir']}");
                $tieDir = $order['dir'] === 'asc' ? 'asc' : 'desc';
            }
            $query->orderBy('id', $tieDir);
        }

        return $query;
    }

    public function findWithDefinition(int $id): ?Record
    {
        return Record::query()
            ->with('recordDefinition')
            ->find($id);
    }

    public function findWithDefinitionForUpdate(int $id): ?Record
    {
        return Record::query()
            ->with('recordDefinition')
            ->whereKey($id)
            ->lockForUpdate()
            ->first();
    }

    public function findTrashed(int $id): ?Record
    {
        return Record::query()
            ->withTrashed()
            ->with('recordDefinition')
            ->find($id);
    }

    public function forceDeleteByDefinition(int $recordDefinitionId): int
    {
        // Bulk hard delete всех записей определения (включая soft-deleted).
        // Без пер-модельных событий — определение удаляется целиком.
        return Record::query()
            ->withTrashed()
            ->where('record_definition_id', $recordDefinitionId)
            ->forceDelete();
    }

    public function findForResponse(int $id): Record
    {
        return Record::query()
            ->withTrashed()
            ->with(['recordDefinition', 'author'])
            ->findOrFail($id);
    }

    public function findByIdsWithDefinition(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Record::query()
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->with('recordDefinition')
            ->get()
            ->all();
    }

    public function paginateForDefinition(int $definitionId, PageRequest $pagination): LengthAwarePaginator
    {
        return Record::query()
            ->with(['recordDefinition', 'author'])
            ->where('record_definition_id', $definitionId)
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(perPage: $pagination->perPage, page: $pagination->page);
    }

    public function findForRead(int $id): ?Record
    {
        return Record::query()
            ->with(['recordDefinition', 'author'])
            ->withTrashed()
            ->find($id);
    }

    public function findHydratableByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Record::query()
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->with(['recordDefinition'])
            ->get()
            ->all();
    }
}
