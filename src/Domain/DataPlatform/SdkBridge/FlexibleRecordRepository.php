<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\SdkBridge;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\StorageKey;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\Records\Core\Contracts\RecordRepository;
use Polymorph\Platform\Domain\Records\Core\Models\Record;
use Polymorph\Platform\Domain\Records\Core\Query\RecordQueryCriteria;
use Polymorph\Platform\Domain\Records\Pipeline\Commands\CreateRecordCommand;
use Polymorph\Platform\Domain\Records\Pipeline\Commands\DeleteRecordCommand;
use Polymorph\Platform\Domain\Records\Pipeline\Commands\UpdateRecordCommand;
use Polymorph\Platform\Domain\Records\Pipeline\Core\RecordSnapshot;
use Polymorph\Platform\Domain\Records\Pipeline\Handlers\CreateRecordHandler;
use Polymorph\Platform\Domain\Records\Pipeline\Handlers\DeleteRecordHandler;
use Polymorph\Platform\Domain\Records\Pipeline\Handlers\UpdateRecordHandler;
use Polymorph\Platform\Domain\Records\Query\RecordQueryCriteriaFactory;
use Polymorph\Platform\Domain\Records\Services\RecordWriteAccessService;
use Polymorph\Platform\Domain\Records\Support\RecordSystemFields;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;
use Polymorph\Platform\Domain\SchemaModel\Services\FieldAccessService;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;
use Polymorph\Sdk\Data\Entity;
use Polymorph\Sdk\Data\EntityPage;
use Polymorph\Sdk\Data\Query;
use Polymorph\Sdk\Data\QueryExecutor;
use Polymorph\Sdk\Data\Repository;
use Polymorph\Sdk\Http\Pagination;

/**
 * Flexible-реализация V2 {@see Repository}/{@see QueryExecutor} поверх платформы
 * данных ядра (Records). Порт V1 SdkRecordStore на V2-контракты:
 *  - привязан к ОДНОЙ сущности расширения (extId+entity) — без definitionCode в каждом вызове;
 *  - возвращает типизированный SDK {@see Entity} (опц. сгенерированный подкласс);
 *  - изоляция: запись доступна, только если принадлежит definition этого расширения.
 *
 * Это host-код (ему можно импортировать ядро). Запросы транслируются через
 * RecordQueryCriteriaFactory::fromParts() — общий источник нормализации с V1.
 *
 * @implements Repository<Entity>
 */
final class FlexibleRecordRepository implements QueryExecutor, Repository
{
    private const PAGE_SIZE = 500;

    private ?RecordDefinition $definition = null;

    /**
     * @param  class-string<Entity>  $entityClass
     */
    public function __construct(
        private readonly CreateRecordHandler $createHandler,
        private readonly UpdateRecordHandler $updateHandler,
        private readonly DeleteRecordHandler $deleteHandler,
        private readonly RecordRepository $records,
        private readonly AuthenticationContext $auth,
        private readonly RecordQueryCriteriaFactory $criteriaFactory,
        private readonly RecordWriteAccessService $writeAccess,
        private readonly FieldAccessService $fieldAccess,
        private readonly string $extensionId,
        private readonly string $entity,
        private readonly string $entityClass = Entity::class,
    ) {}

    public function create(array $data): Entity
    {
        $this->assertActorMayWrite($data);

        $snapshot = $this->createHandler->handle(new CreateRecordCommand(
            recordDefinition: $this->definition(),
            dataJson: $data,
            actorId: $this->actorId(),
        ));

        return $this->fromSnapshot($snapshot);
    }

    public function find(int $id): ?Entity
    {
        $record = $this->records->findWithDefinition($id);

        return $record instanceof Record && $this->belongsHere($record) ? $this->fromModel($record) : null;
    }

    public function update(int $id, array $partial): Entity
    {
        // Проверяется ИМЕННО $partial (что меняем от имени актора), а не merged:
        // merged содержит и скрытые для записи поля, которые актор не трогал, —
        // требование прав на них блокировало бы любое обновление записи.
        $this->assertActorMayWrite($partial);

        return DB::transaction(function () use ($id, $partial): Entity {
            $record = $this->requireForUpdate($id);
            $current = is_array($record->data_json) ? $record->data_json : [];
            $merged = array_merge($this->publicData($current), $partial);

            return $this->writeSnapshot($id, $merged);
        });
    }

    public function replace(int $id, array $data): Entity
    {
        $this->assertActorMayWrite($data);
        $this->require($id);

        return $this->writeSnapshot($id, $data);
    }

    public function delete(int $id): void
    {
        $this->require($id);

        $this->deleteHandler->handle(new DeleteRecordCommand(
            recordId: $id,
            actorId: $this->actorId(),
        ));
    }

    public function all(): array
    {
        $all = [];
        $page = 1;

        do {
            $paginator = $this->records->paginateForDefinition(
                (int) $this->definition()->id,
                new PageRequest($page, self::PAGE_SIZE),
            );

            foreach ($paginator->items() as $record) {
                if ($record instanceof Record) {
                    $all[] = $this->fromModel($record);
                }
            }

            $page++;
        } while ($paginator->hasMorePages());

        return $all;
    }

    public function query(): Query
    {
        return new Query($this);
    }

    public function increment(int $id, string $field, int|float $delta): Entity
    {
        $this->require($id);
        $this->assertNumericField($field);
        // Инкремент — такая же запись в поле, как create/update/replace:
        // incrementField() идёт сырым UPDATE мимо пайплайна, поэтому полевой
        // ACL здесь приходится требовать явно, иначе запрет на поле обходится
        // прибавлением нуля.
        $this->assertActorMayWrite([$field => $delta]);

        $this->records->incrementField($id, $field, $delta);

        $fresh = $this->records->findWithDefinition($id);

        return $this->fromModel($fresh instanceof Record ? $fresh : $this->require($id));
    }

    public function firstOrCreate(array $match, array $defaults = []): Entity
    {
        $query = $this->matchQuery($match);
        $existing = $query->first();
        if ($existing !== null) {
            return $existing;
        }

        try {
            return $this->create([...$match, ...$defaults]);
        } catch (UniqueConstraintViolationException $e) {
            $raced = $query->first();
            if ($raced !== null) {
                return $raced;
            }
            throw $e;
        }
    }

    public function upsert(array $match, array $values = []): Entity
    {
        $query = $this->matchQuery($match);
        $existing = $query->first();
        if ($existing !== null) {
            return $this->update($existing->id, $values);
        }

        try {
            return $this->create([...$match, ...$values]);
        } catch (UniqueConstraintViolationException $e) {
            $raced = $query->first();
            if ($raced !== null) {
                return $this->update($raced->id, $values);
            }
            throw $e;
        }
    }

    // ───────── QueryExecutor ─────────

    public function runGet(Query $query): array
    {
        return $this->records->findForQuery($this->toCriteria($query))
            ->map(fn (Record $record): Entity => $this->fromModel($record))
            ->values()
            ->all();
    }

    public function runFirst(Query $query): ?Entity
    {
        $record = $this->records->firstForQuery($this->toCriteria($query));

        return $record instanceof Record ? $this->fromModel($record) : null;
    }

    public function runExists(Query $query): bool
    {
        return $this->records->existsForQuery($this->toCriteria($query));
    }

    public function runCount(Query $query): int
    {
        return $this->records->countForQuery($this->toCriteria($query));
    }

    public function runPaginate(Query $query, int $page, int $perPage): EntityPage
    {
        $paginator = $this->records->paginateForQuery($this->toCriteria($query), $page, $perPage);

        $items = [];
        foreach ($paginator->items() as $record) {
            if ($record instanceof Record) {
                $items[] = $this->fromModel($record);
            }
        }

        return new EntityPage($items, new Pagination($page, $perPage, (int) $paginator->total()));
    }

    public function runAggregate(Query $query, string $func, string $field): ?float
    {
        $this->assertNumericField($field);

        return $this->records->aggregate($this->toCriteria($query), $func, $field);
    }

    // ───────── Внутреннее ─────────

    private function toCriteria(Query $query): RecordQueryCriteria
    {
        return $this->criteriaFactory->fromParts(
            $this->definition(),
            $query->conditions(),
            $query->authorId(),
            $query->orders(),
            $query->limitValue(),
        );
    }

    /**
     * @param  array<string, mixed>  $match
     */
    private function matchQuery(array $match): Query
    {
        if ($match === []) {
            throw new \InvalidArgumentException('firstOrCreate/upsert require a non-empty match.');
        }

        $query = $this->query();
        foreach ($match as $field => $value) {
            $query->where((string) $field, $value);
        }

        return $query;
    }

    private function definition(): RecordDefinition
    {
        if ($this->definition instanceof RecordDefinition) {
            return $this->definition;
        }

        $schemaCode = StorageKey::schemaCode($this->extensionId, $this->entity);

        $definition = RecordDefinition::query()
            ->whereHas('schema', fn ($q) => $q->where('code', $schemaCode))
            ->first();

        if (! $definition instanceof RecordDefinition) {
            throw new \InvalidArgumentException(
                "Extension definition '{$this->extensionId}.{$this->entity}' is not seeded (use DefinitionRegistry::ensure).",
            );
        }

        return $this->definition = $definition;
    }

    private function require(int $id): Record
    {
        $record = $this->records->findWithDefinition($id);
        if (! $record instanceof Record || ! $this->belongsHere($record)) {
            throw new \InvalidArgumentException("Record {$id} does not belong to '{$this->extensionId}.{$this->entity}'.");
        }

        return $record;
    }

    private function requireForUpdate(int $id): Record
    {
        $record = $this->records->findWithDefinitionForUpdate($id);
        if (! $record instanceof Record || ! $this->belongsHere($record)) {
            throw new \InvalidArgumentException("Record {$id} does not belong to '{$this->extensionId}.{$this->entity}'.");
        }

        return $record;
    }

    private function belongsHere(Record $record): bool
    {
        return (int) $record->record_definition_id === (int) $this->definition()->id;
    }

    private function writeSnapshot(int $id, array $data): Entity
    {
        $snapshot = $this->updateHandler->handle(new UpdateRecordCommand(
            recordId: $id,
            recordDefinition: $this->definition(),
            dataJson: $data,
            actorId: $this->actorId(),
        ));

        return $this->fromSnapshot($snapshot);
    }

    private function fromSnapshot(RecordSnapshot $snapshot): Entity
    {
        $class = $this->entityClass;

        return new $class(
            $snapshot->id->value,
            $this->readableData($this->publicData($snapshot->dataJson)),
            $snapshot->revision->value,
            $this->actorId(),
        );
    }

    private function fromModel(Record $record): Entity
    {
        $class = $this->entityClass;
        $data = is_array($record->data_json) ? $record->data_json : [];

        return new $class(
            (int) $record->id,
            $this->readableData($this->publicData($data)),
            (int) $record->revision,
            $record->author_id === null ? null : (int) $record->author_id,
        );
    }

    /**
     * Field-ACL на SDK-пути (аудит, A7). Раньше HTTP-путь ядра маскировал и
     * охранял поля через RecordReadProfileResolver/RecordWriteAccessService, а
     * плагин, обслуживая запрос того же пользователя, читал и писал всё.
     *
     * Актора нет — фильтрация не применяется: плагин работает со своими данными
     * от своего имени. Это не только консоль, джобы и lifecycle-хуки: в ту же
     * ветку попадает ЛЮБОЙ запрос без аутентификации, включая публичную зону
     * маршрутов плагина (ZoneKind::API даёт middleware ['api'], без auth). То
     * есть на публичном эндпоинте плагин сам отвечает за то, что отдаёт.
     * Актор есть — те же правила видимости/записи, что и на пути ядра.
     *
     * ВАЖНО: update() мержит partial с НЕфильтрованным текущим data_json —
     * скрытые от актора поля не теряются при записи.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function readableData(array $data): array
    {
        $actor = $this->auth->actor();
        if (! $actor instanceof UserIdentity) {
            return $data;
        }

        $schemaId = $this->definition()->schema_id;
        if (! is_int($schemaId) || $schemaId <= 0) {
            return $data;
        }

        return $this->fieldAccess->filterReadableDataJson($actor, $schemaId, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertActorMayWrite(array $data): void
    {
        $actor = $this->auth->actor();
        if (! $actor instanceof UserIdentity) {
            return;
        }

        $this->writeAccess->assertCanWriteRecordDefinitionPayload($actor, $this->definition(), $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function publicData(array $data): array
    {
        $forbidden = RecordSystemFields::forbiddenPayloadSystemKeys($data);

        return $forbidden === [] ? $data : array_diff_key($data, array_flip($forbidden));
    }

    private function actorId(): ?int
    {
        $identifier = $this->auth->authIdentifier();

        if (is_int($identifier)) {
            return $identifier;
        }

        if (is_string($identifier) && ctype_digit($identifier)) {
            return (int) $identifier;
        }

        return null;
    }

    private function assertNumericField(string $field): void
    {
        $model = Field::query()
            ->where('schema_id', (int) $this->definition()->schema_id)
            ->where('full_path', $field)
            ->first(['type']);

        if ($model === null) {
            throw new \InvalidArgumentException("Field '{$field}' does not exist on '{$this->extensionId}.{$this->entity}'.");
        }

        if (! in_array($model->type, [FieldType::INT, FieldType::FLOAT], true)) {
            throw new \InvalidArgumentException("Field '{$field}' is not numeric and cannot be aggregated/incremented.");
        }
    }
}
