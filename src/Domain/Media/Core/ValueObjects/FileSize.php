<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object для размера файла.
 *
 * Обеспечивает удобные методы конвертации и форматирования.
 */
final readonly class FileSize
{
    private function __construct(
        private int $bytes
    ) {
        if ($bytes < 0) {
            throw new InvalidArgumentException("File size cannot be negative: {$bytes}");
        }
    }

    /**
     * Создать из байтов.
     */
    public static function fromBytes(int $bytes): self
    {
        return new self($bytes);
    }

    /**
     * Создать из килобайт.
     */
    public static function fromKB(float $kb): self
    {
        return new self((int) round($kb * 1024));
    }

    /**
     * Создать из мегабайт.
     */
    public static function fromMB(float $mb): self
    {
        return new self((int) round($mb * 1024 * 1024));
    }

    /**
     * Получить размер в байтах.
     */
    public function toBytes(): int
    {
        return $this->bytes;
    }

    /**
     * Получить размер в килобайтах.
     */
    public function toKB(): float
    {
        return round($this->bytes / 1024, 2);
    }

    /**
     * Получить размер в мегабайтах.
     */
    public function toMB(): float
    {
        return round($this->bytes / (1024 * 1024), 2);
    }

    /**
     * Получить размер в гигабайтах.
     */
    public function toGB(): float
    {
        return round($this->bytes / (1024 * 1024 * 1024), 2);
    }

    /**
     * Форматированный размер для отображения.
     *
     * @return string Например: "1.5 MB", "234 KB", "5.2 GB"
     */
    public function toHumanReadable(): string
    {
        if ($this->bytes < 1024) {
            return $this->bytes . ' B';
        }

        if ($this->bytes < 1024 * 1024) {
            return $this->toKB() . ' KB';
        }

        if ($this->bytes < 1024 * 1024 * 1024) {
            return $this->toMB() . ' MB';
        }

        return $this->toGB() . ' GB';
    }

    /**
     * Проверить, превышает ли размер указанное значение в мегабайтах.
     */
    public function exceedsMB(float $mb): bool
    {
        return $this->bytes > ($mb * 1024 * 1024);
    }

    /**
     * Сравнить с другим размером.
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->bytes > $other->bytes;
    }

    /**
     * Сравнить с другим размером.
     */
    public function isLessThan(self $other): bool
    {
        return $this->bytes < $other->bytes;
    }
}
