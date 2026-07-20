<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object для размеров изображения.
 *
 * Хранит ширину и высоту с методами для работы с пропорциями.
 */
final readonly class MediaDimensions
{
    private function __construct(
        private int $width,
        private int $height
    ) {
        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException(
                "Dimensions must be positive: {$width}x{$height}"
            );
        }
    }

    /**
     * Создать из ширины и высоты.
     */
    public static function from(int $width, int $height): self
    {
        return new self($width, $height);
    }

    /**
     * Получить ширину.
     */
    public function width(): int
    {
        return $this->width;
    }

    /**
     * Получить высоту.
     */
    public function height(): int
    {
        return $this->height;
    }

    /**
     * Получить соотношение сторон (width/height).
     */
    public function aspectRatio(): float
    {
        return round($this->width / $this->height, 4);
    }

    /**
     * Получить длинную сторону.
     */
    public function longSide(): int
    {
        return max($this->width, $this->height);
    }

    /**
     * Получить короткую сторону.
     */
    public function shortSide(): int
    {
        return min($this->width, $this->height);
    }

    /**
     * Проверить, является ли изображение портретным.
     */
    public function isPortrait(): bool
    {
        return $this->height > $this->width;
    }

    /**
     * Проверить, является ли изображение альбомным.
     */
    public function isLandscape(): bool
    {
        return $this->width > $this->height;
    }

    /**
     * Проверить, является ли изображение квадратным.
     */
    public function isSquare(): bool
    {
        return $this->width === $this->height;
    }

    /**
     * Рассчитать размеры при масштабировании до максимальной длинной стороны.
     *
     * @param int $maxLongSide Максимальный размер длинной стороны
     * @return self Новые размеры с сохранением пропорций
     */
    public function scaleToFit(int $maxLongSide): self
    {
        if ($this->longSide() <= $maxLongSide) {
            return $this;
        }

        $scale = $maxLongSide / $this->longSide();

        return new self(
            max(1, (int) round($this->width * $scale)),
            max(1, (int) round($this->height * $scale))
        );
    }

    /**
     * Строковое представление.
     */
    public function toString(): string
    {
        return "{$this->width}Г—{$this->height}";
    }

    /**
     * Приведение к строке.
     */
    public function __toString(): string
    {
        return $this->toString();
    }
}
