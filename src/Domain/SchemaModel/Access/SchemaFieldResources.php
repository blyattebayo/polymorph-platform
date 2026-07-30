<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Access;

use Polymorph\Platform\SharedKernel\Access\ResourceRef;

/**
 * Единственный источник ACL-ресурсов полевого дерева схемы.
 *
 * До него формат 'schema.{id}.fields[.{path}]' собирался строками в двух местах
 * (FieldAccessService и RecordReadProfileResolver) — молчаливое расхождение
 * формата означало бы «всё разрешено» на проверке корня (аудит, C3).
 */
final class SchemaFieldResources
{
    public static function root(int $schemaId): ResourceRef
    {
        return ResourceRef::of('schema', (string) $schemaId, 'fields');
    }

    public static function field(int $schemaId, string $fieldPath): ResourceRef
    {
        return ResourceRef::fromString(self::root($schemaId)->value.'.'.$fieldPath);
    }
}
