<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Infrastructure\Images;

use Polymorph\Platform\Domain\Media\Core\Contracts\ImageProcessor;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\ImageRef;
use RuntimeException;

/**
 * Реализация ImageProcessor на базе PHP GD
 *
 * Поддерживаемые форматы: JPEG, PNG, GIF, WebP
 * Ограничения: нет поддержки HEIC/AVIF
 */
final class GdImageProcessor implements ImageProcessor
{
    /**
     * Открыть изображение из бинарных данных
     */
    public function open(string $contents): ImageRef
    {
        /** @var \GdImage|false $image */
        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw new RuntimeException(
                'GD: Failed to decode image data. The data may be corrupted or in an unsupported format.'
            );
        }

        return new ImageRef($image);
    }

    /**
     * Изменить размер изображения
     */
    public function resize(ImageRef $image, int $width, int $height): ImageRef
    {
        /** @var \GdImage $gd */
        $gd = $image->native;

        // Если размер уже совпадает, возвращаем исходное изображение
        if (imagesx($gd) === $width && imagesy($gd) === $height) {
            return $image;
        }

        // Создаем новое изображение с нужным размером
        $resampled = imagecreatetruecolor($width, $height);

        if ($resampled === false) {
            throw new RuntimeException(
                "GD: Failed to create image resource for resizing to {$width}x{$height}"
            );
        }

        // Сохраняем прозрачность для PNG/GIF
        imagealphablending($resampled, false);
        imagesavealpha($resampled, true);

        // Ресемплинг с сохранением качества
        $success = imagecopyresampled(
            $resampled,
            $gd,
            0,
            0,
            0,
            0,
            $width,
            $height,
            imagesx($gd),
            imagesy($gd)
        );

        if (! $success) {
            imagedestroy($resampled);
            $originalWidth = imagesx($gd);
            $originalHeight = imagesy($gd);
            throw new RuntimeException(
                "GD: Failed to resample image from {$originalWidth}x{$originalHeight} to {$width}x{$height}"
            );
        }

        // Освобождаем исходное изображение
        imagedestroy($gd);

        return new ImageRef($resampled);
    }

    /**
     * Закодировать изображение в формат
     */
    public function encode(ImageRef $image, string $format, int $quality = 90): array
    {
        /** @var \GdImage $gd */
        $gd = $image->native;

        // Нормализуем качество
        $quality = max(0, min(100, $quality));
        $format = strtolower($format);

        // Нормализация расширений
        $extension = match ($format) {
            'jpg', 'jpeg' => 'jpg',
            'png' => 'png',
            'gif' => 'gif',
            'webp' => 'webp',
            default => throw new RuntimeException("GD: Unsupported image format '{$format}'"),
        };

        $mimeType = ImageMimeTypes::fromExtension($extension)
            ?? throw new RuntimeException("GD: Unsupported image format '{$extension}'");

        ob_start();

        try {
            match ($extension) {
                'png' => $this->encodePng($gd, $quality),
                'gif' => $this->encodeGif($gd),
                'webp' => $this->encodeWebp($gd, $quality),
                'jpg' => $this->encodeJpeg($gd, $quality),
            };

            $data = ob_get_clean();

            if ($data === false || $data === '') {
                throw new RuntimeException(
                    "GD: Failed to encode image to {$extension} format - output buffer is empty"
                );
            }

            return [
                'data' => $data,
                'extension' => $extension,
                'mime' => $mimeType,
            ];
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Получить ширину изображения
     */
    public function getWidth(ImageRef $image): int
    {
        /** @var \GdImage $gd */
        $gd = $image->native;

        return imagesx($gd);
    }

    /**
     * Получить высоту изображения
     */
    public function getHeight(ImageRef $image): int
    {
        /** @var \GdImage $gd */
        $gd = $image->native;

        return imagesy($gd);
    }

    /**
     * Освободить ресурсы изображения
     */
    public function destroy(ImageRef $image): void
    {
        /** @var \GdImage $gd */
        $gd = $image->native;
        imagedestroy($gd);
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
        $formats = ['jpg', 'jpeg', 'png', 'gif'];

        if (function_exists('imagewebp')) {
            $formats[] = 'webp';
        }

        return $formats;
    }

    /**
     * Получить имя процессора
     */
    public function name(): string
    {
        return 'gd';
    }

    /**
     * Закодировать в PNG
     */
    private function encodePng(\GdImage $gd, int $quality): void
    {
        // PNG использует compression level 0-9 (обратная шкала качества)
        // Конвертируем quality 0-100 в compression 0-9
        $compression = (int) round((100 - $quality) / 11.111);
        $compression = max(0, min(9, $compression));

        if (! imagepng($gd, null, $compression)) {
            throw new RuntimeException(
                "GD: Failed to encode PNG with compression level {$compression}"
            );
        }
    }

    /**
     * Закодировать в GIF
     */
    private function encodeGif(\GdImage $gd): void
    {
        if (! imagegif($gd)) {
            throw new RuntimeException('GD: Failed to encode GIF image');
        }
    }

    /**
     * Закодировать в WebP
     */
    private function encodeWebp(\GdImage $gd, int $quality): void
    {
        if (! function_exists('imagewebp')) {
            throw new RuntimeException(
                'GD: WebP support is not available in this PHP installation'
            );
        }

        if (! imagewebp($gd, null, $quality)) {
            throw new RuntimeException(
                "GD: Failed to encode WebP image with quality {$quality}"
            );
        }
    }

    /**
     * Закодировать в JPEG
     */
    private function encodeJpeg(\GdImage $gd, int $quality): void
    {
        if (! imagejpeg($gd, null, $quality)) {
            throw new RuntimeException(
                "GD: Failed to encode JPEG image with quality {$quality}"
            );
        }
    }
}
