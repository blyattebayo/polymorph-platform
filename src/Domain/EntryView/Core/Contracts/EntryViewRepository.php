<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Core\Contracts;

use Polymorph\Platform\Domain\EntryView\Core\Models\EntryView;

/**
 * Репозиторий для работы с конфигурациями форм.
 *
 * Определяет контракт для операций с EntryView,
 * изолируя бизнес-логику от конкретной реализации хранилища.
 */
interface EntryViewRepository
{
    /**
     * Найти конфигурацию по RecordDefinition и Schema.
     *
     * @param  int  $recordDefinitionId  ID типа контента
     * @param  int  $schemaId  ID схемы
     * @return EntryView|null Найденная конфигурация или null
     */
    public function findByRecordDefinitionAndSchema(int $recordDefinitionId, int $schemaId): ?EntryView;

    /**
     * Создать или обновить конфигурацию (upsert).
     *
     * @param  int  $recordDefinitionId  ID типа контента
     * @param  int  $schemaId  ID схемы
     * @param  array<string, mixed>  $configJson  JSON конфигурации
     * @return EntryView Созданная или обновленная конфигурация
     */
    public function createOrUpdate(int $recordDefinitionId, int $schemaId, array $configJson): EntryView;
}
