<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Core;

/**
 * Формат ключа макета карточки: пара идентификаторов в непрозрачной строке
 * `{recordDefinition}:{schema}`.
 *
 * Ключ склеивает клиент — бэкенд его не разбирает и существование пары не
 * проверяет. Формат нужен здесь для обратной задачи: снять строки, оставшиеся
 * от удалённого определения записи или схемы, внешних ключей у них нет.
 */
final class EntryViewConfigKey
{
    private const SEPARATOR = ':';

    public static function for(int $recordDefinitionId, int $schemaId): string
    {
        return $recordDefinitionId.self::SEPARATOR.$schemaId;
    }

    /**
     * LIKE-шаблон всех макетов одного определения записи: идентификатор стоит
     * первым сегментом ключа.
     */
    public static function ofRecordDefinition(int $recordDefinitionId): string
    {
        return $recordDefinitionId.self::SEPARATOR.'%';
    }

    /**
     * LIKE-шаблон всех макетов одной схемы: разделитель в ключе ровно один,
     * поэтому суффикс однозначно опознаёт второй сегмент.
     */
    public static function ofSchema(int $schemaId): string
    {
        return '%'.self::SEPARATOR.$schemaId;
    }
}
