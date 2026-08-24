<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Core;

/**
 * Грамматика ключа макета карточки: `entry_view:{recordDefinition}`.
 *
 * Макет принадлежит типу контента, а не схеме: раскладку задают не только поля,
 * а схему у типа контента можно сменить — макет это переживает, потому что
 * мёртвые ссылки на поля деградируют в рантайме.
 *
 * Ключ склеивает клиент — бэкенд его не разбирает и существования цели не
 * проверяет. Формат нужен здесь для обратной задачи: снять строку, оставшуюся от
 * удалённого типа контента, внешнего ключа у неё нет.
 */
final class EntryViewConfigKey
{
    private const PREFIX = 'entry_view';

    public static function for(int $recordDefinitionId): string
    {
        return self::PREFIX.':'.$recordDefinitionId;
    }
}
