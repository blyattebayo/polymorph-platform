<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects;

/**
 * Enum для типов полей.
 *
 * Строгая типизация вместо строк, интеллектуальная поддержка в IDE.
 */
enum FieldType: string
{
    case STRING = 'string';
    case TEXT = 'text';
    case INT = 'int';
    case FLOAT = 'float';
    case BOOL = 'bool';
    case DATETIME = 'datetime';
    case JSON = 'json';
    case REF = 'ref';
    case MEDIA = 'media';

    /**
     * Является ли контейнером для вложенных полей.
     */
    public function isContainer(): bool
    {
        return $this === self::JSON;
    }

    /**
     * Postgres-каст для (data_json->>key)::cast при фильтрации/индексации по полю.
     * null = сравнивать как текст. Единый источник для query-API и материализации
     * индексов. datetime НЕ кастуем: text→timestamptz STABLE (недопустим в
     * index-выражении), а ISO-8601 UTC сортируется/сравнивается лексикографически.
     */
    public function sqlCast(): ?string
    {
        return match ($this) {
            self::INT => 'bigint',
            self::FLOAT => 'numeric',
            self::BOOL => 'boolean',
            default => null,
        };
    }
}
