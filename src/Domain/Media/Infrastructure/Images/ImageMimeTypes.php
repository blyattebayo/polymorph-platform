<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Infrastructure\Images;

/**
 * Единая карта «расширение → MIME» для драйверов обработки изображений.
 * Каждый драйвер поддерживает своё подмножество форматов — карта описывает
 * полный набор, а драйвер сам ограничивает допустимые расширения.
 */
final class ImageMimeTypes
{
    private const BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'heic' => 'image/heic',
    ];

    private function __construct() {}

    public static function fromExtension(string $extension): ?string
    {
        return self::BY_EXTENSION[strtolower($extension)] ?? null;
    }
}
