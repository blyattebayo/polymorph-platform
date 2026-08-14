<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\SdkBridge;

use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;

/** Canonical value object for extension-owned definition codes: `ext__{extension}__{entity}`. */
final readonly class ExtensionStorageKey
{
    public const PREFIX = 'ext';

    public const SEPARATOR = '__';

    private function __construct(
        public string $extensionId,
        public string $entity,
    ) {}

    public static function for(string $extensionId, string $entity): self
    {
        $extensionId = trim($extensionId);
        $entity = trim($entity);
        if ($extensionId === '' || $entity === '') {
            throw DataPlatformBadRequest::because(
                'invalid_extension_storage_key',
                'Extension storage keys require non-empty extension and entity names.',
            );
        }

        return new self($extensionId, $entity);
    }

    public static function schemaCode(string $extensionId, string $entity): string
    {
        return self::for($extensionId, $entity)->value();
    }

    public function value(): string
    {
        return self::schemaPrefix($this->extensionId).$this->entity;
    }

    private static function schemaPrefix(string $extensionId): string
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
