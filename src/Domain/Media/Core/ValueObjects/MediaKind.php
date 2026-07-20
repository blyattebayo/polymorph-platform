<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\ValueObjects;

/**
 * Enum для типов медиа-файлов.
 *
 * Определяет типы медиа: изображения, видео, аудио, документы.
 * Используется для типобезопасной работы с типами медиа вместо строковых значений.
 */
enum MediaKind: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';

    /**
     * Определить тип медиа по MIME-типу.
     *
     * Анализирует MIME-тип и возвращает соответствующий MediaKind:
     * - image/* в†’ Image
     * - video/* в†’ Video
     * - audio/* в†’ Audio
     * - остальное → Document
     *
     * @param  string  $mime  MIME-тип файла
     * @return self Тип медиа-файла
     */
    public static function fromMime(string $mime): self
    {
        return match (true) {
            str_starts_with($mime, 'image/') => self::Image,
            str_starts_with($mime, 'video/') => self::Video,
            str_starts_with($mime, 'audio/') => self::Audio,
            default => self::Document,
        };
    }

    /**
     * Название типа для UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Image => 'Изображение',
            self::Video => 'Видео',
            self::Audio => 'Аудио',
            self::Document => 'Документ',
        };
    }
}
