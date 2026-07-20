<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform;

/**
 * Единый источник правды для internal storage key схем расширений: код схемы
 * строится из id расширения и голого имени сущности — 'ext__{id}__{entity}'.
 *
 * Разделитель — не точка (точка запрещена slug-грамматикой кода схемы), а '__'.
 * Это НЕ ACL namespace (тот — 'ext.{id}.…'); хранилищный ключ и ACL-ресурс —
 * разные пространства. И сидер, и рантайм-доступ обязаны звать этот класс.
 */
final class StorageKey
{
    public const PREFIX = 'ext';

    public const SEPARATOR = '__';

    private function __construct()
    {
    }

    public static function schemaCode(string $extensionId, string $entity): string
    {
        return self::schemaPrefix($extensionId) . trim($entity);
    }

    /** Префикс кодов всех схем расширения: 'ext__{id}__'. */
    public static function schemaPrefix(string $extensionId): string
    {
        return self::PREFIX . self::SEPARATOR . trim($extensionId) . self::SEPARATOR;
    }

    /**
     * Обратная операция к schemaCode(): разбирает 'ext__{id}__{entity}' обратно на
     * extensionId + entity. Возвращает null, если код не принадлежит расширению
     * (например, код схемы ядра без префикса 'ext__').
     *
     * @return array{extensionId: string, entity: string}|null
     */
    public static function parse(string $schemaCode): ?array
    {
        $prefix = self::PREFIX . self::SEPARATOR; // 'ext__'
        if (! str_starts_with($schemaCode, $prefix)) {
            return null;
        }

        $rest = substr($schemaCode, strlen($prefix)); // '{id}__{entity}'
        $sepAt = strpos($rest, self::SEPARATOR);
        if ($sepAt === false) {
            return null;
        }

        $extensionId = substr($rest, 0, $sepAt);
        $entity = substr($rest, $sepAt + strlen(self::SEPARATOR));

        if ($extensionId === '' || $entity === '') {
            return null;
        }

        return ['extensionId' => $extensionId, 'entity' => $entity];
    }
}
