<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Infrastructure\Images;

use Polymorph\Platform\Domain\Media\Core\Contracts\ImageProcessor;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\ImageRef;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;

/**
 * Реализация ImageProcessor на базе Intervention Image
 *
 * Поддерживаемые форматы: JPEG, PNG, GIF, WebP, AVIF, HEIC (зависит от драйвера)
 * Драйвер (GD/Imagick) выбирается при инициализации ImageManager
 */
final class GlideImageProcessor implements ImageProcessor
{
    public function __construct(
        private readonly ImageManager $manager
    ) {
    }

    /**
     * Открыть изображение из бинарных данных
     */
    public function open(string $contents): ImageRef
    {
        try {
            $img = $this->manager->read($contents);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Intervention Image: Failed to decode image data. ' . $e->getMessage(),
                previous: $e
            );
        }

        return new ImageRef($img);
    }

    /**
     * Изменить размер изображения
     */
    public function resize(ImageRef $image, int $width, int $height): ImageRef
    {
        /** @var ImageInterface $im */
        $im = $image->native;

        // Если размер уже совпадает, возвращаем исходное изображение
        if ($im->width() === $width && $im->height() === $height) {
            return $image;
        }

        // Масштабирование до точных размеров
        $resized = $im->scale($width, $height);

        return new ImageRef($resized);
    }

    /**
     * Закодировать изображение в формат
     */
    public function encode(ImageRef $image, string $format, int $quality = 90): array
    {
        /** @var ImageInterface $im */
        $im = $image->native;

        $format = strtolower($format);
        $quality = max(0, min(100, $quality));

        // Нормализация расширений и fallback
        $extension = match ($format) {
            'jpg', 'jpeg' => 'jpg',
            'png' => 'png',
            'gif' => 'gif',
            'webp' => 'webp',
            'avif' => 'avif',
            'heic', 'heif' => 'heic', // может fallback на jpg
            default => throw new RuntimeException(
                "Intervention Image: Unsupported image format '{$format}'"
            ),
        };

        try {
            $data = match ($extension) {
                'png' => (string) $im->toPng(),
                'gif' => (string) $im->toGif(),
                'webp' => (string) $im->toWebp(quality: $quality),
                'avif' => $this->encodeAvif($im, $quality),
                'heic' => $this->encodeHeic($im, $quality),
                'jpg' => (string) $im->toJpeg(quality: $quality),
            };

            if ($data === '') {
                throw new RuntimeException(
                    "Intervention Image: Encoding to {$extension} format produced empty output"
                );
            }

            // Определяем реальное расширение после fallback
            $actualExtension = $this->detectExtensionFromData($data) ?? $extension;

            // MIME типы
            $mimeType = match ($actualExtension) {
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'avif' => 'image/avif',
                'heic' => 'image/heic',
                default => 'application/octet-stream',
            };

            return [
                'data' => $data,
                'extension' => $actualExtension,
                'mime' => $mimeType,
            ];
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Intervention Image: Failed to encode image to {$format} format. " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Определить расширение из данных изображения (по magic bytes)
     */
    private function detectExtensionFromData(string $data): ?string
    {
        $header = substr($data, 0, 12);
        
        if (str_starts_with($header, "\xFF\xD8\xFF")) {
            return 'jpg';
        }
        if (str_starts_with($header, "\x89PNG\r\n\x1a\n")) {
            return 'png';
        }
        if (str_starts_with($header, "GIF87a") || str_starts_with($header, "GIF89a")) {
            return 'gif';
        }
        if (str_starts_with($header, "RIFF") && str_contains(substr($header, 0, 16), "WEBP")) {
            return 'webp';
        }
        
        return null;
    }

    /**
     * Получить ширину изображения
     */
    public function getWidth(ImageRef $image): int
    {
        /** @var ImageInterface $im */
        $im = $image->native;
        return $im->width();
    }

    /**
     * Получить высоту изображения
     */
    public function getHeight(ImageRef $image): int
    {
        /** @var ImageInterface $im */
        $im = $image->native;
        return $im->height();
    }

    /**
     * Освободить ресурсы изображения
     */
    public function destroy(ImageRef $image): void
    {
        // Intervention Image использует управление памятью PHP
        // Явная очистка не требуется
    }

    /**
     * Проверить, поддерживается ли формат
     */
    public function supports(string $format): bool
    {
        $format = strtolower($format);
        return in_array($format, $this->supportedFormats(), true);
    }

    /**
     * Получить список поддерживаемых форматов
     */
    public function supportedFormats(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'heic', 'heif'];
    }

    /**
     * Получить имя процессора
     */
    public function name(): string
    {
        return 'glide';
    }

    /**
     * Закодировать в AVIF с fallback на JPEG
     */
    private function encodeAvif(ImageInterface $im, int $quality): string
    {
        try {
            return (string) $im->toAvif(quality: $quality);
        } catch (\Throwable) {
            // Fallback на JPEG если AVIF не поддерживается
            return (string) $im->toJpeg(quality: $quality);
        }
    }

    /**
     * Закодировать в HEIC с fallback на JPEG
     */
    private function encodeHeic(ImageInterface $im, int $quality): string
    {
        try {
            // Проверяем наличие метода toHeic
            if (method_exists($im, 'toHeic')) {
                /** @phpstan-ignore-next-line */
                return (string) $im->toHeic(quality: $quality);
            }

            // Fallback на JPEG
            return (string) $im->toJpeg(quality: $quality);
        } catch (\Throwable) {
            // Fallback на JPEG если HEIC не поддерживается
            return (string) $im->toJpeg(quality: $quality);
        }
    }
}
