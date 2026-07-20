<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform;

use Polymorph\Platform\Domain\DataPlatform\SdkBridge\FlexibleRecordRepository;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Commands\CreateRecordDefinitionCommand;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Handlers\CreateRecordDefinitionHandler;
use Polymorph\Platform\Domain\Records\Core\Contracts\RecordRepository;
use Polymorph\Platform\Domain\Records\Pipeline\Handlers\CreateRecordHandler;
use Polymorph\Platform\Domain\Records\Pipeline\Handlers\DeleteRecordHandler;
use Polymorph\Platform\Domain\Records\Pipeline\Handlers\UpdateRecordHandler;
use Polymorph\Platform\Domain\Records\Query\RecordQueryCriteriaFactory;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Commands\SaveSchemaWithFieldsCommand;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Handlers\SaveSchemaWithFieldsHandler;
use Polymorph\Platform\SharedKernel\Identity\CurrentActorResolver;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwner;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwnershipService;
use Polymorph\Platform\SharedKernel\Ownership\ResourceType;
use Polymorph\Platform\Support\Validation\ValidationConstraints;
use Polymorph\Sdk\Data\DefinitionRef;
use Polymorph\Sdk\Data\Entity;
use Polymorph\Sdk\Data\FieldDefinition;
use Polymorph\Sdk\Data\Repository;
use Polymorph\Sdk\Data\SchemaSpec;

/**
 * ЕДИНЫЙ внутренний шов платформы данных для Extension SDK V2.
 *
 * Прячет пайплайны SchemaModel/Records за одной узкой поверхностью: host-адаптеры
 * SDK зависят только от этого фасада, а не от ~15 классов ядра напрямую (как
 * толстый bridge V1). Любой рефактор пайплайнов рябит только здесь (см. ADR 0002).
 *
 * Сейчас закрыта definition-часть (сидинг схем). CRUD/query записей добавляются
 * следующим инкрементом, тоже через этот фасад.
 *
 * Владение ресурсами расширения временно использует ResourceOwner::plugin($id)
 * (таксономия ownership пока plugin-keyed; обобщение до 'extension' — отдельно).
 */
final class DataPlatform
{
    public function __construct(
        private readonly SaveSchemaWithFieldsHandler $schemaHandler,
        private readonly CreateRecordDefinitionHandler $definitionHandler,
        private readonly ResourceOwnershipService $ownership,
        private readonly CreateRecordHandler $createRecordHandler,
        private readonly UpdateRecordHandler $updateRecordHandler,
        private readonly DeleteRecordHandler $deleteRecordHandler,
        private readonly RecordRepository $records,
        private readonly CurrentActorResolver $actors,
        private readonly RecordQueryCriteriaFactory $queryCriteriaFactory,
    ) {}

    /**
     * Скоупнутый на (расширение, сущность) репозиторий записей — flexible-бэкенд.
     * Единственная точка получения CRUD/query: host-адаптеры/DI берут отсюда.
     *
     * @param  class-string<Entity>  $entityClass  опциональный сгенерированный подкласс (codegen)
     * @return Repository<Entity>
     */
    public function repository(string $extensionId, string $entity, string $entityClass = Entity::class): Repository
    {
        return new FlexibleRecordRepository(
            $this->createRecordHandler,
            $this->updateRecordHandler,
            $this->deleteRecordHandler,
            $this->records,
            $this->actors,
            $this->queryCriteriaFactory,
            $extensionId,
            $entity,
            $entityClass,
        );
    }

    public function ensureDefinition(string $extensionId, string $entity, SchemaSpec $spec): DefinitionRef
    {
        $entity = $this->assertEntity($entity);
        $schemaCode = StorageKey::schemaCode($extensionId, $entity);
        $name = trim($spec->name) !== '' ? trim($spec->name) : $entity;

        if ($spec->fields === []) {
            throw new \InvalidArgumentException("Definition '{$extensionId}.{$entity}' must declare at least one field.");
        }

        $schema = SchemaModel::query()->where('code', $schemaCode)->first();

        if (! $schema instanceof SchemaModel) {
            $schema = $this->schemaHandler->handle(new SaveSchemaWithFieldsCommand(
                schemaPayload: [
                    'name' => $name,
                    'code' => $schemaCode,
                    'metadata' => ['owner' => 'extension', 'extension_id' => $extensionId, 'entity' => $entity],
                ],
                fieldsUpsert: $this->toFieldsUpsert($spec->fields),
                fieldsDelete: [],
            ));
        } else {
            $missing = $this->missingFields($schema, $spec->fields);
            if ($missing !== []) {
                $schema = $this->schemaHandler->handle(new SaveSchemaWithFieldsCommand(
                    schemaPayload: ['name' => (string) $schema->name, 'code' => $schemaCode],
                    fieldsUpsert: $this->toFieldsUpsert($missing),
                    fieldsDelete: [],
                    schema: $schema,
                ));
            }
        }

        $owner = ResourceOwner::plugin($extensionId);
        $this->ownership->set(ResourceType::SCHEMA, (int) $schema->id, $owner);

        $definition = RecordDefinition::query()->where('schema_id', (int) $schema->id)->first();
        if (! $definition instanceof RecordDefinition) {
            $definition = $this->definitionHandler->handle(new CreateRecordDefinitionCommand([
                'name' => $name,
                'schema_id' => (int) $schema->id,
            ]));
        }
        $this->ownership->set(ResourceType::RECORD_DEFINITION, (int) $definition->id, $owner);

        return new DefinitionRef(
            id: (int) $definition->id,
            schemaId: (int) $schema->id,
            entity: $entity,
        );
    }

    /**
     * @param  list<FieldDefinition>  $fields
     * @return list<array<string, mixed>>
     */
    private function toFieldsUpsert(array $fields): array
    {
        $upsert = [];

        foreach ($fields as $field) {
            $upsert[] = [
                'name' => $field->name,
                'type' => $field->type->value,
                'cardinality' => $field->cardinality->value,
                'is_indexed' => $field->indexed,
                'sort_order' => $field->sortOrder,
                'validation_rules' => $field->rules !== [] ? $field->rules : null,
                // unique → metadata (НЕ в validation_rules: иначе уйдёт в Laravel-валидатор).
                // Материализуется ядром в partial UNIQUE-индекс.
                'metadata' => $field->unique ? ['unique' => true] : null,
            ];
        }

        return $upsert;
    }

    /**
     * @param  list<FieldDefinition>  $fields
     * @return list<FieldDefinition>
     */
    private function missingFields(SchemaModel $schema, array $fields): array
    {
        $existing = array_fill_keys(
            $schema->fields()->pluck('name')->map(static fn ($name): string => (string) $name)->all(),
            true,
        );

        return array_values(array_filter(
            $fields,
            static fn (FieldDefinition $field): bool => ! isset($existing[$field->name]),
        ));
    }

    private function assertEntity(string $entity): string
    {
        $entity = trim($entity);

        if (! ValidationConstraints::slug()->matches($entity)) {
            throw new \InvalidArgumentException(
                'Extension definition entity must be a slug ('.ValidationConstraints::slug()->pattern()."), got '{$entity}'.",
            );
        }

        return $entity;
    }
}
