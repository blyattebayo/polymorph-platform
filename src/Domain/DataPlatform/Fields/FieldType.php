<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields;

/** Built-in field types mirrored by the public SDK contract. */
enum FieldType: string
{
    case STRING = 'string';
    case TEXT = 'text';
    case INT = 'int';
    case FLOAT = 'float';
    case BOOL = 'bool';
    case DATETIME = 'datetime';
    case JSON = 'json';
    case RAW_JSON = 'raw_json';
    case REF = 'ref';
    case MEDIA = 'media';

    public function isNumeric(): bool
    {
        return $this === self::INT || $this === self::FLOAT;
    }
}
