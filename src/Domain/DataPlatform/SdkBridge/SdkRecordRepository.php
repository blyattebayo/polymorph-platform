<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\SdkBridge;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\Domain\DataPlatform\Control\SchemaCatalog;
use Polymorph\Platform\Domain\DataPlatform\Delete\RecordDeleteCommand;
use Polymorph\Platform\Domain\DataPlatform\Delete\RecordDeleteCommandHandler;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Fields\Cardinality;
use Polymorph\Platform\Domain\DataPlatform\Fields\DocumentPathAccessor;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Query\BooleanNode;
use Polymorph\Platform\Domain\DataPlatform\Query\PredicateNode;
use Polymorph\Platform\Domain\DataPlatform\Query\QueryPlanner;
use Polymorph\Platform\Domain\DataPlatform\Query\QuerySpec;
use Polymorph\Platform\Domain\DataPlatform\Query\RecordQueryService;
use Polymorph\Platform\Domain\DataPlatform\Read\LogicalDocumentReader;
use Polymorph\Platform\Domain\DataPlatform\Read\RecordReadService;
use Polymorph\Platform\Domain\DataPlatform\Write\RecordCommandBus;
use Polymorph\Platform\Domain\DataPlatform\Write\RecordStore;
use Polymorph\Platform\Domain\DataPlatform\Write\RecordWriteCommand;
use Polymorph\Platform\Domain\DataPlatform\Write\RecordWriteResult;
use Polymorph\Platform\Domain\DataPlatform\Write\StoredRecord;
use Polymorph\Sdk\Data\Entity;
use Polymorph\Sdk\Data\EntityPage;
use Polymorph\Sdk\Data\Query;
use Polymorph\Sdk\Data\QueryExecutor;
use Polymorph\Sdk\Data\Repository;
use Polymorph\Sdk\Http\Pagination;

/** SDK adapter over the command/query/read services; it contains no storage writes. */
final class SdkRecordRepository implements QueryExecutor, Repository
{
    /** @param class-string<Entity> $entityClass */
    public function __construct(
        private readonly int $definitionId,
        private readonly string $entityClass,
        private readonly RecordCommandBus $writes,
        private readonly RecordDeleteCommandHandler $deletes,
        private readonly RecordReadService $reads,
        private readonly RecordQueryService $queries,
        private readonly QueryPlanner $planner,
        private readonly SchemaCatalog $schemas,
        private readonly AuthenticationContext $auth,
        private readonly RecordStore $records,
        private readonly DocumentPathAccessor $paths,
        private readonly LogicalDocumentReader $logicalDocuments,
    ) {}

    public function create(array $data): Entity
    {
        return $this->fromWrite($this->writes->dispatch(new RecordWriteCommand(
            $this->definitionId,
            $data,
            $this->actorId(),
        )));
    }

    public function find(int $id): ?Entity
    {
        $record = $this->reads->find($id, $this->actorId());

        return is_array($record) && (int) $record['record_definition_id'] === $this->definitionId
            ? $this->fromRow($record)
            : null;
    }

    public function update(int $id, array $partial): Entity
    {
        $record = $this->requireStored($id);

        return $this->fromWrite($this->writes->dispatch(new RecordWriteCommand(
            $this->definitionId,
            $partial,
            $this->actorId(),
            recordId: $id,
            expectedRevision: (int) $record->revision,
            replace: false,
        )));
    }

    public function replace(int $id, array $data): Entity
    {
        $record = $this->requireStored($id);

        return $this->fromWrite($this->writes->dispatch(new RecordWriteCommand(
            $this->definitionId,
            $data,
            $this->actorId(),
            recordId: $id,
            expectedRevision: (int) $record->revision,
            replace: true,
        )));
    }

    public function delete(int $id): void
    {
        $record = $this->requireStored($id);
        $this->deletes->execute(new RecordDeleteCommand($id, $this->actorId(), (int) $record->revision));
    }

    public function all(): array
    {
        $all = [];
        $page = 1;
        do {
            $result = $this->queries->execute(new QuerySpec($this->definitionId, page: $page, perPage: 500), $this->actorId());
            $rows = $result['data'];
            if ($rows === []) {
                break;
            }
            array_push($all, ...array_map($this->fromRow(...), $rows));
            $page++;
        } while (count($all) < (int) $result['meta']['total']);

        return $all;
    }

    public function query(): Query
    {
        return new Query($this);
    }

    public function increment(int $id, string $field, int|float $delta): Entity
    {
        $definition = $this->numericField($field);

        return DB::transaction(function () use ($id, $definition, $delta): Entity {
            $record = $this->records->lockActive($id);
            if (! $record instanceof StoredRecord || $record->definitionId !== $this->definitionId) {
                throw DataPlatformBadRequest::because(
                    'record_definition_mismatch',
                    "Record {$id} does not belong to definition {$this->definitionId}.",
                    ['record_id' => $id, 'record_definition_id' => $this->definitionId],
                );
            }
            $logical = $this->logicalDocuments->current(
                $record->definitionId,
                $record->schemaVersionId,
                $record->document,
            )['document'];
            [, $current] = $this->paths->get($logical, $definition->path);
            $current ??= 0;
            if (! is_int($current) && ! is_float($current)) {
                throw DataPlatformBadRequest::because(
                    'field_value_not_numeric',
                    "Field '{$definition->path}' does not contain a numeric value.",
                    ['field' => $definition->path],
                );
            }
            $patch = $this->paths->set([], $definition->path, $current + $delta);

            return $this->fromWrite($this->writes->dispatch(new RecordWriteCommand(
                $this->definitionId,
                $patch,
                $this->actorId(),
                recordId: $id,
                expectedRevision: $record->revision,
                replace: false,
            )));
        }, 3);
    }

    public function firstOrCreate(array $match, array $defaults = []): Entity
    {
        $existing = $this->matchQuery($match)->first();
        if ($existing !== null) {
            return $existing;
        }
        try {
            return $this->create([...$match, ...$defaults]);
        } catch (UniqueConstraintViolationException $exception) {
            return $this->matchQuery($match)->first() ?? throw $exception;
        }
    }

    public function upsert(array $match, array $values = []): Entity
    {
        $existing = $this->matchQuery($match)->first();
        if ($existing !== null) {
            return $this->update($existing->id, $values);
        }
        try {
            return $this->create([...$match, ...$values]);
        } catch (UniqueConstraintViolationException $exception) {
            $raced = $this->matchQuery($match)->first();

            return $raced === null ? throw $exception : $this->update($raced->id, $values);
        }
    }

    public function runGet(Query $query): array
    {
        $limit = $query->limitValue() ?? 500;
        $result = $this->queries->execute($this->spec($query, 1, min(500, max(1, $limit))), $this->actorId());

        return array_map($this->fromRow(...), array_slice($result['data'], 0, $limit));
    }

    public function runFirst(Query $query): ?Entity
    {
        $result = $this->queries->execute($this->spec($query, 1, 1), $this->actorId());

        return isset($result['data'][0]) ? $this->fromRow($result['data'][0]) : null;
    }

    public function runExists(Query $query): bool
    {
        return $this->runCount($query) > 0;
    }

    public function runCount(Query $query): int
    {
        return (int) $this->queries->execute($this->spec($query, 1, 1), $this->actorId())['meta']['total'];
    }

    public function runPaginate(Query $query, int $page, int $perPage): EntityPage
    {
        $result = $this->queries->execute($this->spec($query, $page, min(500, $perPage)), $this->actorId());

        return new EntityPage(
            array_map($this->fromRow(...), $result['data']),
            new Pagination($page, $perPage, (int) $result['meta']['total']),
        );
    }

    public function runAggregate(Query $query, string $func, string $field): ?float
    {
        $this->numericField($field);
        $value = $this->planner->aggregate($this->spec($query, 1, 1), $this->actorId(), $func, $field);

        return $value === null ? null : (float) $value;
    }

    private function spec(Query $query, int $page, int $perPage): QuerySpec
    {
        $nodes = [];
        foreach ($query->conditions() as $condition) {
            $operator = match ($condition['op']) {
                '=' => 'eq',
                '<' => 'lt', '<=' => 'lte', '>' => 'gt', '>=' => 'gte',
                'in' => 'in', 'isnull' => 'is_null', 'notnull' => 'is_not_null',
                '!=' => 'eq',
                default => throw DataPlatformBadRequest::because(
                    'unsupported_sdk_query_operator',
                    "Unsupported SDK query operator '{$condition['op']}'.",
                    ['operator' => $condition['op']],
                ),
            };
            $predicate = new PredicateNode($condition['field'], $operator, $condition['value']);
            $nodes[] = $condition['op'] === '!=' ? new BooleanNode('not', [$predicate]) : $predicate;
        }
        if ($query->authorId() !== null) {
            $nodes[] = new PredicateNode('$author_id', 'eq', $query->authorId());
        }
        $filter = match (count($nodes)) {
            0 => null,
            1 => $nodes[0],
            default => new BooleanNode('and', $nodes),
        };
        $sort = array_map(static fn (array $order): array => [
            'field' => $order['field'], 'direction' => $order['dir'],
        ], $query->orders());

        return new QuerySpec($this->definitionId, $filter, $sort, $page, $perPage, allowScan: true);
    }

    private function matchQuery(array $match): Query
    {
        if ($match === []) {
            throw DataPlatformBadRequest::because(
                'empty_sdk_match',
                'firstOrCreate/upsert require a non-empty match.',
            );
        }
        $query = $this->query();
        foreach ($match as $field => $value) {
            $query->where((string) $field, $value);
        }

        return $query;
    }

    private function requireStored(int $id): StoredRecord
    {
        $record = $this->records->findActive($id);
        if (! $record instanceof StoredRecord || $record->definitionId !== $this->definitionId) {
            throw DataPlatformBadRequest::because(
                'record_definition_mismatch',
                "Record {$id} does not belong to definition {$this->definitionId}.",
                ['record_id' => $id, 'record_definition_id' => $this->definitionId],
            );
        }

        return $record;
    }

    private function numericField(string $identifier): FieldDefinition
    {
        $schema = $this->schemas->writableDefinition($this->definitionId);
        foreach ($schema['fields'] as $field) {
            if (($field->id === $identifier || $field->path === $identifier)
                && in_array($field->type, ['int', 'float'], true)
                && $field->cardinality === Cardinality::ONE
                && ! $field->multiValued) {
                return $field;
            }
        }
        throw DataPlatformBadRequest::because(
            'field_not_numeric',
            "Field '{$identifier}' is not numeric.",
            ['field' => $identifier],
        );
    }

    private function fromWrite(RecordWriteResult $result): Entity
    {
        $class = $this->entityClass;

        return new $class($result->recordId, $result->document, $result->revision, $this->actorId());
    }

    /** @param array<string,mixed> $row */
    private function fromRow(array $row): Entity
    {
        $class = $this->entityClass;

        return new $class(
            (int) $row['id'],
            (array) $row['data'],
            (int) $row['revision'],
            isset($row['author_id']) ? (int) $row['author_id'] : null,
        );
    }

    private function actorId(): ?int
    {
        return $this->auth->userId();
    }
}
