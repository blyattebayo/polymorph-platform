<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Infrastructure\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Polymorph\Platform\Domain\Media\Core\Contracts\StorageResolver as StorageResolverContract;
use Polymorph\Platform\Domain\Media\Core\Exceptions\MediaStorageException;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind;

/**
 * Реализация резолвера дисков хранения для медиа
 *
 * Определяет диск на основе типа медиа используя конфигурацию:
 * - config/media.php: media.disks.kinds
 * - config/media.php: media.disks.default
 */
final class StorageResolver implements StorageResolverContract
{
    /**
     * Получить диск для типа медиа
     */
    public function resolveDisk(MediaKind $kind): Filesystem
    {
        $diskName = $this->resolveDiskName($kind);

        return $this->getDisk($diskName);
    }

    /**
     * Получить имя диска для типа медиа
     */
    public function resolveDiskName(MediaKind $kind): string
    {
        /** @var array<string, mixed> $disksConfig */
        $disksConfig = config('media.disks', []);

        /** @var array<string, string> $kinds */
        $kinds = (array) ($disksConfig['kinds'] ?? []);

        // Проверяем наличие конфигурации для конкретного типа
        $kindValue = $kind->value;
        if (isset($kinds[$kindValue]) && is_string($kinds[$kindValue])) {
            return $kinds[$kindValue];
        }

        // Используем дефолтный диск
        $default = $disksConfig['default'] ?? null;
        if (is_string($default) && $default !== '') {
            return $default;
        }

        // Жесткий fallback
        return 'media';
    }

    /**
     * Определить тип медиа по MIME-типу
     */
    public function resolveKind(string $mimeType): MediaKind
    {
        return MediaKind::fromMime($mimeType);
    }

    /**
     * Получить диск по его имени
     */
    public function getDisk(string $diskName): Filesystem
    {
        try {
            return Storage::disk($diskName);
        } catch (\InvalidArgumentException $e) {
            throw MediaStorageException::diskNotFound($diskName);
        }
    }
}
