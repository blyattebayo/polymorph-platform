<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\SdkBridge;

/** Canonical codec for extension-owned definition codes: `ext__{extension}__{entity}`. */
final class ExtensionStorageKey
{
    public const PREFIX = 'ext';

    public const SEPARATOR = '__';

    private function __construct() {}

    public static function schemaCode(string $extensionId, string $entity): string
    {
        return self::schemaPrefix($extensionId).trim($entity);
    }

    public static function schemaPrefix(string $extensionId): string
    {
        return self::PREFIX.self::SEPARATOR.trim($extensionId).self::SEPARATOR;
    }

    /** @return array{extensionId: string, entity: string}|null */
    public static function parse(string $schemaCode): ?array
    {
        $prefix = self::PREFIX.self::SEPARATOR;
        if (! str_starts_with($schemaCode, $prefix)) {
            return null;
        }

        $rest = substr($schemaCode, strlen($prefix));
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
