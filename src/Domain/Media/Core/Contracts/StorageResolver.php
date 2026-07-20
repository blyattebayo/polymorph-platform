<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Contracts;

use Illuminate\Contracts\Filesystem\Filesystem;
use Polymorph\Platform\Domain\Media\Core\Exceptions\MediaStorageException;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind;

/**
 * Определение диска хранения для медиа-файлов
 */
interface StorageResolver
{
    /**
     * Получить диск для типа медиа
     *
     * @param  MediaKind  $kind  Тип медиа (image/video/audio/document)
     * @return Filesystem Экземпляр диска хранения
     */
    public function resolveDisk(MediaKind $kind): Filesystem;

    /**
     * Получить имя диска для типа медиа
     *
     * @param  MediaKind  $kind  Тип медиа
     * @return string Имя диска (например, 'media_images')
     */
    public function resolveDiskName(MediaKind $kind): string;

    /**
     * Определить тип медиа по MIME-типу
     *
     * @param  string  $mimeType  MIME-тип файла
     * @return MediaKind Тип медиа
     */
    public function resolveKind(string $mimeType): MediaKind;

    /**
     * Получить диск по его имени
     *
     * @param  string  $diskName  Имя диска
     * @return Filesystem Экземпляр диска хранения
     *
     * @throws MediaStorageException Если диск не найден
     */
    public function getDisk(string $diskName): Filesystem;
}
