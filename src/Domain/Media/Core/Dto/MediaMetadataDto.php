<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Dto;

/**
 * DTO для нормализованных метаданных медиа-файла.
 *
 * Представляет унифицированную структуру метаданных, извлечённых
 * из различных источников (ImageProcessor, ffprobe, mediainfo, exiftool).
 *
 * Данные используются для создания записей в специализированных таблицах:
 * - width, height → MediaImage (для изображений)
 * - durationMs, bitrateKbps, frameRate, frameCount, videoCodec, audioCodec → MediaAvMetadata (для видео/аудио)
 */
readonly class MediaMetadataDto
{
    /**
     * @param  int|null  $width  Ширина изображения в пикселях
     * @param  int|null  $height  Высота изображения в пикселях
     * @param  int|null  $durationMs  Длительность медиа в миллисекундах
     * @param  int|null  $bitrateKbps  Битрейт в килобитах в секунду
     * @param  float|null  $frameRate  Частота кадров в секунду
     * @param  int|null  $frameCount  Количество кадров
     * @param  string|null  $videoCodec  Кодек видео
     * @param  string|null  $audioCodec  Кодек аудио
     */
    public function __construct(
        public ?int $width = null,
        public ?int $height = null,
        public ?int $durationMs = null,
        public ?int $bitrateKbps = null,
        public ?float $frameRate = null,
        public ?int $frameCount = null,
        public ?string $videoCodec = null,
        public ?string $audioCodec = null
    ) {
        // DTO - это просто контейнер данных, валидация происходит на уровне Request
    }
}
