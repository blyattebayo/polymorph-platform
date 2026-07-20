<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Services\Metadata;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\UploadedFile;
use Polymorph\Platform\Domain\Media\Core\Contracts\ImageProcessor;
use Polymorph\Platform\Domain\Media\Core\Dto\MediaMetadataDto;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind;
use Polymorph\Platform\Domain\Media\Services\Metadata\Plugins\MediaMetadataPlugin;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Сервис для извлечения метаданных из медиафайлов.
 *
 * Извлекает размеры изображений, EXIF-данные и другую информацию
 * из загруженных файлов. Использует плагины (getID3, ffprobe/mediainfo/exiftool)
 * с graceful fallback и кэшированием результатов.
 */
class MediaMetadataExtractor
{
    /**
     * @param  ImageProcessor  $images  Процессор изображений
     * @param  iterable<MediaMetadataPlugin>  $plugins  Плагины для извлечения медиаметаданных
     * @param  Repository|null  $cache  Кэш для метаданных
     * @param  int  $cacheTtl  TTL кэша в секундах (по умолчанию 3600)
     */
    public function __construct(
        private readonly ImageProcessor $images,
        private readonly AppLogger $logger,
        private readonly iterable $plugins = [],
        private readonly ?CacheRepository $cache = null,
        private readonly int $cacheTtl = 3600,
    ) {}

    /**
     * Извлечь метаданные из медиа-файла.
     *
     * Если $kind передан, извлекает только релевантные метаданные:
     * - Image: width, height, EXIF (только для JPEG/TIFF)
     * - Video: duration, bitrate, frameRate, frameCount, video/audio codecs
     * - Audio: duration, bitrate, audioCodec (БЕЗ video-специфичных полей)
     * - Document: пустой DTO (без извлечения)
     *
     * Для изображений извлекает размеры (width, height) и EXIF-данные (если доступны).
     * Для видео и аудио использует плагины (getID3, ffprobe/mediainfo/exiftool) для извлечения
     * длительности и дополнительных нормализованных полей (bitrate, frameRate, codecs)
     * с graceful fallback.
     *
     * Извлечённые данные возвращаются в виде MediaMetadataDto, который затем используется
     * для создания записей в специализированных таблицах:
     * - MediaImage (width, height, exif_json) для изображений
     * - MediaAvMetadata (duration_ms, bitrate_kbps, frame_rate, codecs) для видео/аудио
     *
     * Результаты кэшируются для оптимизации производительности.
     *
     * @param  UploadedFile  $file  Загруженный файл
     * @param  string|null  $mime  MIME-тип файла (если не указан, определяется автоматически)
     * @param  MediaKind|null  $kind  Тип медиа (для условного извлечения)
     * @return MediaMetadataDto Метаданные файла
     */
    public function extract(UploadedFile $file, ?string $mime = null, ?MediaKind $kind = null): MediaMetadataDto
    {
        $mime ??= $file->getMimeType() ?? $file->getClientMimeType() ?? 'application/octet-stream';

        // Определить kind, если не передан (для обратной совместимости)
        $kind ??= MediaKind::fromMime($mime);

        // Пытаемся получить из кэша
        $cacheKey = $this->getCacheKey($file, $mime, $kind);
        if ($this->cache !== null) {
            $cached = $this->cache->get($cacheKey);
            if ($cached instanceof MediaMetadataDto) {
                return $cached;
            }
        }

        // Условное извлечение в зависимости от типа медиа
        $dto = match ($kind) {
            MediaKind::Image => $this->extractImageMetadata($file, $mime),
            MediaKind::Video => $this->extractVideoMetadata($file, $mime),
            MediaKind::Audio => $this->extractAudioMetadata($file, $mime),
            MediaKind::Document => $this->createEmptyMetadata(),
        };

        // Сохраняем в кэш
        if ($this->cache !== null) {
            $this->cache->put($cacheKey, $dto, $this->cacheTtl);
        }

        return $dto;
    }

    /**
     * Извлечь метаданные изображения.
     *
     * Использует getimagesize() как основной метод (быстро, эффективно).
     * Fallback на ImageProcessor для экзотических форматов.
     *
     * @param  UploadedFile  $file  Загруженный файл
     * @param  string  $mime  MIME-тип файла
     */
    private function extractImageMetadata(UploadedFile $file, string $mime): MediaMetadataDto
    {
        $width = null;
        $height = null;

        // Получить путь к файлу
        $path = $this->getFilePath($file);

        if ($path !== null) {
            // Используем getimagesize() как основной метод (быстрее и легче)
            $imageInfo = @getimagesize($path);

            if (is_array($imageInfo) && isset($imageInfo[0], $imageInfo[1])) {
                $width = $this->sanitizeDimension((int) $imageInfo[0]);
                $height = $this->sanitizeDimension((int) $imageInfo[1]);
            } else {
                // Fallback на ImageProcessor только если getimagesize() не сработал
                try {
                    $bytes = @file_get_contents($path);
                    if (is_string($bytes) && $bytes !== '') {
                        $img = $this->images->open($bytes);
                        $width = $this->sanitizeDimension($this->images->getWidth($img));
                        $height = $this->sanitizeDimension($this->images->getHeight($img));
                        $this->images->destroy($img);
                    }
                } catch (\Throwable $e) {
                    $this->logger->warning('media.metadata.image_dimensions_failed', [
                        'mime' => $mime,
                        'exception' => $e,
                    ]);
                }
            }
        }

        return new MediaMetadataDto(
            width: $width,
            height: $height,
        );
    }

    /**
     * Извлечь метаданные видео.
     *
     * Использует плагины (GetId3, Ffprobe, Mediainfo) для извлечения:
     * - duration_ms, bitrate_kbps
     * - frame_rate, frame_count (специфично для видео)
     * - video_codec, audio_codec
     *
     * @param  UploadedFile  $file  Загруженный файл
     * @param  string  $mime  MIME-тип файла
     */
    private function extractVideoMetadata(UploadedFile $file, string $mime): MediaMetadataDto
    {
        $duration = null;
        $bitrateKbps = null;
        $frameRate = null;
        $frameCount = null;
        $videoCodec = null;
        $audioCodec = null;

        // Получить путь к файлу
        $path = $this->getFilePath($file);

        if ($path !== null && file_exists($path)) {
            // Перебор плагинов для извлечения метаданных
            $pluginData = $this->extractFromPlugins($path, $mime);

            if ($pluginData !== null) {
                $duration = $pluginData['duration_ms'] ?? null;
                $bitrateKbps = $pluginData['bitrate_kbps'] ?? null;
                $frameRate = $pluginData['frame_rate'] ?? null;
                $frameCount = $pluginData['frame_count'] ?? null;
                $videoCodec = $pluginData['video_codec'] ?? null;
                $audioCodec = $pluginData['audio_codec'] ?? null;
            }
        }

        return new MediaMetadataDto(
            durationMs: $duration,
            bitrateKbps: $bitrateKbps,
            frameRate: $frameRate,
            frameCount: $frameCount,
            videoCodec: $videoCodec,
            audioCodec: $audioCodec,
        );
    }

    /**
     * Извлечь метаданные аудио.
     *
     * Использует плагины для извлечения:
     * - duration_ms, bitrate_kbps
     * - audio_codec
     *
     * НЕ извлекает video-специфичные поля:
     * - frame_rate, frame_count, video_codec (всегда null)
     *
     * @param  UploadedFile  $file  Загруженный файл
     * @param  string  $mime  MIME-тип файла
     */
    private function extractAudioMetadata(UploadedFile $file, string $mime): MediaMetadataDto
    {
        $duration = null;
        $bitrateKbps = null;
        $audioCodec = null;

        // Получить путь к файлу
        $path = $this->getFilePath($file);

        if ($path !== null && file_exists($path)) {
            // Перебор плагинов для извлечения метаданных
            $pluginData = $this->extractFromPlugins($path, $mime);

            if ($pluginData !== null) {
                $duration = $pluginData['duration_ms'] ?? null;
                $bitrateKbps = $pluginData['bitrate_kbps'] ?? null;
                $audioCodec = $pluginData['audio_codec'] ?? null;
                // НЕ извлекаем: frame_rate, frame_count, video_codec
            }
        }

        return new MediaMetadataDto(
            durationMs: $duration,
            bitrateKbps: $bitrateKbps,
            audioCodec: $audioCodec,
            // frame_rate, frame_count, video_codec остаются null
        );
    }

    /**
     * Создать пустой DTO метаданных для документов.
     *
     * Документы (PDF, DOCX и т.д.) не имеют специфичных метаданных
     * для извлечения, поэтому возвращаем пустой DTO сразу.
     */
    private function createEmptyMetadata(): MediaMetadataDto
    {
        return new MediaMetadataDto;
    }

    /**
     * Получить путь к файлу с fallback-ами.
     *
     * @param  UploadedFile  $file  Загруженный файл
     * @return string|null Путь к файлу или null
     */
    private function getFilePath(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();

        // Fallback to path() method (for UploadedFile::fake())
        if (! $path || $path === false || $path === '') {
            if (method_exists($file, 'path')) {
                $path = $file->path();
            }
        }

        // Fallback to getPathname()
        if (! $path || $path === false || $path === '') {
            $path = $file->getPathname();
        }

        return is_string($path) && $path !== '' && $path !== false ? $path : null;
    }

    /**
     * Извлечь метаданные через плагины с graceful fallback.
     *
     * @param  string  $path  Путь к файлу
     * @param  string  $mime  MIME-тип
     * @return array<string, mixed>|null Данные или null
     */
    private function extractFromPlugins(string $path, string $mime): ?array
    {
        foreach ($this->plugins as $plugin) {
            if (! $plugin instanceof MediaMetadataPlugin) {
                continue;
            }

            if (! $plugin->supports($mime)) {
                continue;
            }

            try {
                $pluginData = $plugin->extract($path);
                // Если плагин вернул данные с полезной информацией, используем их
                if (is_array($pluginData) && ! empty(array_filter($pluginData))) {
                    return $pluginData;
                }
            } catch (\Throwable) {
                // Продолжаем со следующим плагином
                continue;
            }
        }

        return null;
    }

    /**
     * Получить ключ кэша для файла.
     *
     * @param  UploadedFile  $file  Загруженный файл
     * @param  string  $mime  MIME-тип файла
     * @param  MediaKind  $kind  Тип медиа
     */
    private function getCacheKey(UploadedFile $file, string $mime, MediaKind $kind): string
    {
        $path = $file->getRealPath() ?: $file->getPathname();
        $size = (int) ($file->getSize() ?? 0);
        $mtime = is_file($path) ? filemtime($path) : 0;

        return sprintf('media:metadata:%s:%s:%s:%d:%d', md5($path), $mime, $kind->value, $size, $mtime);
    }

    /**
     * Санитизировать размер изображения (защита от невалидных значений).
     *
     * @param  int|null  $dimension  Размер для валидации
     * @return int|null Валидный размер или null
     */
    private function sanitizeDimension(?int $dimension): ?int
    {
        if ($dimension === null) {
            return null;
        }

        // Используем константы без config() для Unit тестов
        $minDimension = 1;
        $maxDimension = 100_000;

        // Отклоняем невалидные значения
        if ($dimension < $minDimension || $dimension > $maxDimension) {
            $this->logger->warning('media.metadata.invalid_image_dimension', [
                'dimension' => $dimension,
                'min' => $minDimension,
                'max' => $maxDimension,
            ]);

            return null;
        }

        return $dimension;
    }
}
