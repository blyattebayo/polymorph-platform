<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Contracts;

use Polymorph\Platform\Domain\Media\Core\ValueObjects\ImageRef;

/**
 * Обработка изображений (resize, crop, encode)
 */
interface ImageProcessor
{
    /**
     * Открыть изображение из бинарных данных
     *
     * @param string $contents Бинарные данные изображения
     * @return ImageRef Ссылка на изображение в памяти
     * @throws \RuntimeException Если не удалось открыть изображение
     */
    public function open(string $contents): ImageRef;

    /**
     * Изменить размер изображения
     *
     * @param ImageRef $image Изображение
     * @param int $width Новая ширина
     * @param int $height Новая высота
     * @return ImageRef Изменённое изображение
     */
    public function resize(ImageRef $image, int $width, int $height): ImageRef;

    /**
     * Закодировать изображение в формат
     *
     * @param ImageRef $image Изображение
     * @param string $format Формат (jpeg, png, webp, gif, avif, heic)
     * @param int $quality Качество (0-100)
     * @return array{data: string, extension: string, mime: string} Данные изображения и метаинформация
     * @throws \RuntimeException Если формат не поддерживается
     */
    public function encode(ImageRef $image, string $format, int $quality = 90): array;

    /**
     * Получить ширину изображения
     */
    public function getWidth(ImageRef $image): int;

    /**
     * Получить высоту изображения
     */
    public function getHeight(ImageRef $image): int;

    /**
     * Освободить ресурсы изображения
     */
    public function destroy(ImageRef $image): void;

    /**
     * Проверить, поддерживается ли формат
     */
    public function supports(string $format): bool;

    /**
     * Получить список поддерживаемых форматов
     *
     * @return array<int, string>
     */
    public function supportedFormats(): array;

    /**
     * Получить имя процессора (gd, imagick, glide)
     */
    public function name(): string;
}
