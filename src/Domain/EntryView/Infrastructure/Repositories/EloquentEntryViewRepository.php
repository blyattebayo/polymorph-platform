<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Infrastructure\Repositories;

use Polymorph\Platform\Domain\EntryView\Core\Contracts\EntryViewRepository;
use Polymorph\Platform\Domain\EntryView\Core\Models\EntryView;

/**
 * Eloquent реализация репозитория конфигураций форм.
 *
 * Реализует все операции с EntryView через Eloquent ORM,
 * изолируя бизнес-логику от деталей персистентности.
 */
final class EloquentEntryViewRepository implements EntryViewRepository
{
    /**
     * Найти конфигурацию по RecordDefinition и Schema.
     *
     * @param  int  $recordDefinitionId  ID типа контента
     * @param  int  $schemaId  ID схемы
     * @return EntryView|null Найденная конфигурация или null
     */
    public function findByRecordDefinitionAndSchema(int $recordDefinitionId, int $schemaId): ?EntryView
    {
        return EntryView::query()
            ->where('record_definition_id', $recordDefinitionId)
            ->where('schema_id', $schemaId)
            ->first();
    }

    /**
     * Создать или обновить конфигурацию (upsert).
     *
     * @param  int  $recordDefinitionId  ID типа контента
     * @param  int  $schemaId  ID схемы
     * @param  array<string, mixed>  $configJson  JSON конфигурации
     * @return EntryView Созданная или обновленная конфигурация
     */
    public function createOrUpdate(int $recordDefinitionId, int $schemaId, array $configJson): EntryView
    {
        $config = EntryView::query()->updateOrCreate(
            [
                'record_definition_id' => $recordDefinitionId,
                'schema_id' => $schemaId,
            ],
            [
                'config_json' => $configJson,
            ]
        );

        return $config;
    }
}
