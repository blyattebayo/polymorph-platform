<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Services;

use Polymorph\Platform\Domain\Media\Core\Contracts\ImageProcessor;
use Polymorph\Platform\Domain\Media\Core\Exceptions\InvalidMediaTypeException;
use Polymorph\Platform\Domain\Media\Core\Exceptions\MediaStorageException;
use Polymorph\Platform\Domain\Media\Core\Exceptions\VariantGenerationException;
use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Core\Models\MediaVariant;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaVariantStatus;
use Polymorph\Platform\Domain\Media\Events\MediaProcessed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * Сервис для генерации вариантов медиа-файлов по требованию.
 *
 * Генерирует варианты изображений (thumbnails, resized) на лету
 * через абстракцию ImageProcessor (gd/imagick/glide/external).
 *
 * @package Polymorph\Platform\Domain\Media\Services
 */
class OnDemandVariantService
{
    /**
     * @param \Polymorph\Platform\Domain\Media\Core\Contracts\ImageProcessor $images Процессор изображений
     */
    public function __construct(
        private readonly ImageProcessor $images
    ) {
    }

    /**
     * Убедиться, что вариант существует на диске и в БД.
     *
     * Проверяет существование варианта. Если вариант отсутствует или его файл не существует,
     * генерирует вариант синхронно. Для preview-запросов всегда генерирует синхронно,
     * чтобы обеспечить немедленную доступность файла.
     *
     * @param \Polymorph\Platform\Domain\Media\Core\Models\Media $media Медиа-файл
     * @param string $variant Имя варианта
     * @return \Polymorph\Platform\Domain\Media\Core\Models\MediaVariant Созданный или существующий вариант
     * @throws InvalidMediaTypeException Если медиа не поддерживает варианты
     * @throws VariantGenerationException Если вариант не настроен или не удалось сгенерировать
     * @throws MediaStorageException Если не удалось прочитать файл
     */
    public function ensureVariant(Media $media, string $variant): MediaVariant
    {
        $this->assertSupportsVariants($media, $variant);

        $existing = $media->variants()
            ->where('variant', $variant)
            ->first();

        // Если вариант существует и файл есть на диске, возвращаем его
        if ($existing && $existing->status === MediaVariantStatus::Ready && Storage::disk($media->disk)->exists($existing->path)) {
            return $existing;
        }

        // Генерируем вариант синхронно, чтобы обеспечить немедленную доступность
        return $this->generateVariant($media, $variant);
    }

    /**
     * Сгенерировать вариант медиа-файла.
     *
     * Читает оригинальный файл, изменяет размер (если нужно),
     * кодирует в нужный формат и сохраняет на диск.
     * После успешной генерации отправляет событие MediaProcessed.
     *
     * @param \Polymorph\Platform\Domain\Media\Core\Models\Media $media Медиа-файл
     * @param string $variant Имя варианта
     * @return \Polymorph\Platform\Domain\Media\Core\Models\MediaVariant Созданный вариант
     * @throws InvalidMediaTypeException Если медиа не поддерживает варианты
     * @throws VariantGenerationException Если вариант не настроен или не удалось сгенерировать
     * @throws MediaStorageException Если не удалось прочитать/записать файл
     */
    public function generateVariant(Media $media, string $variant): MediaVariant
    {
        $this->assertSupportsVariants($media, $variant);

        $config = config("media.variants.{$variant}");
        $targetMax = (int) ($config['max'] ?? 0);
        $variantFormat = isset($config['format']) ? (string) $config['format'] : null;
        $variantQuality = isset($config['quality']) ? (int) $config['quality'] : null;

        $disk = Storage::disk($media->disk);

        $stream = $disk->readStream($media->path);

        if (! $stream) {
            throw MediaStorageException::readFailed(
                $media->disk,
                $media->path
            );
        }

        $contents = stream_get_contents($stream);
        fclose($stream);

        if ($contents === false) {
            throw MediaStorageException::readFailed(
                $media->disk,
                $media->path
            );
        }

        // Обработка изображения с перехватом ошибок
        try {
            $image = $this->images->open($contents);

            $originalWidth = $this->images->getWidth($image);
            $originalHeight = $this->images->getHeight($image);

            $targetWidth = $originalWidth;
            $targetHeight = $originalHeight;

            if ($targetMax > 0) {
                $longSide = max($originalWidth, $originalHeight);

                if ($longSide > $targetMax) {
                    $scale = $targetMax / $longSide;
                    $targetWidth = max(1, (int) round($originalWidth * $scale));
                    $targetHeight = max(1, (int) round($originalHeight * $scale));
                }
            }

            $resized = $this->images->resize($image, $targetWidth, $targetHeight);

            $preferredExt = $variantFormat ?: ($media->ext ?? pathinfo($media->path, PATHINFO_EXTENSION));
            $quality = $variantQuality ?? (int) (config('media.image.quality', 82));
            $encoded = $this->images->encode($resized, (string) $preferredExt, $quality);
        } catch (\Throwable $e) {
            throw VariantGenerationException::forVariant(
                $variant,
                $media->id,
                'Image processing failed: ' . $e->getMessage(),
                $e
            );
        }

        $variantPath = $this->buildVariantPath($media, $variant, $encoded['extension']);

        // Сохранение файла с перехватом ошибок
        try {
            $disk->put($variantPath, $encoded['data']);
        } catch (\Throwable $e) {
            throw MediaStorageException::writeFailed(
                $media->disk,
                $variantPath,
                $e
            );
        }

        $sizeBytes = $disk->size($variantPath);

        // Обновляем/создаём запись варианта и фиксируем прогресс
        $variantModel = MediaVariant::updateOrCreate(
            ['media_id' => $media->id, 'variant' => $variant],
            [
                'path' => $variantPath,
                'width' => $this->images->getWidth($resized),
                'height' => $this->images->getHeight($resized),
                'size_bytes' => $sizeBytes ?: strlen($encoded['data']),
                'status' => MediaVariantStatus::Ready,
                'error_message' => null,
                'finished_at' => now('UTC'),
            ]
        );

        $this->images->destroy($resized);

        // Отправляем событие обработки медиа-файла
        Event::dispatch(new MediaProcessed($media, $variantModel));

        return $variantModel;
    }

    /**
     * Проверить, что медиа поддерживает варианты и вариант настроен.
     *
     * @param \Polymorph\Platform\Domain\Media\Core\Models\Media $media Медиа-файл
     * @param string $variant Имя варианта
     * @return void
     * @throws InvalidMediaTypeException Если медиа не изображение
     * @throws VariantGenerationException Если вариант не настроен
     */
    private function assertSupportsVariants(Media $media, string $variant): void
    {
        if ($media->kind() !== MediaKind::Image) {
            throw InvalidMediaTypeException::invalidOperationForKind(
                'variant generation',
                $media->kind()
            );
        }

        $variants = config('media.variants', []);

        if (! array_key_exists($variant, $variants)) {
            $availableVariants = array_keys($variants);
            throw VariantGenerationException::forVariant(
                $variant,
                $media->id,
                sprintf(
                    'Variant is not configured. Available variants: %s',
                    empty($availableVariants) ? 'none' : implode(', ', $availableVariants)
                )
            );
        }
    }

    /**
     * Построить путь для варианта.
     *
     * Формат: {original-filename}-{variant}.{extension}
     *
     * @param \Polymorph\Platform\Domain\Media\Core\Models\Media $media Медиа-файл
     * @param string $variant Имя варианта
     * @param string $extension Расширение файла
     * @return string Путь к варианту
     */
    private function buildVariantPath(Media $media, string $variant, string $extension): string
    {
        $pathInfo = pathinfo($media->path);
        $directory = $pathInfo['dirname'] ?? '';
        $originalExtension = strtolower($pathInfo['extension'] ?? $media->ext ?? 'jpg');
        $filename = $pathInfo['filename'] ?? basename($media->path, '.'.$originalExtension);

        $variantFilename = "{$filename}-{$variant}.{$extension}";

        return ltrim($directory === '.' ? $variantFilename : "{$directory}/{$variantFilename}", '/');
    }
}


