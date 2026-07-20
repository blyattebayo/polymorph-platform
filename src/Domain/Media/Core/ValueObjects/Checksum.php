<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object для SHA256 checksum медиа-файла.
 *
 * Обеспечивает валидность и типобезопасность checksum.
 */
final readonly class Checksum
{
    private function __construct(
        private string $value
    ) {
        if (!$this->isValid($value)) {
            throw new InvalidArgumentException("Invalid SHA256 checksum: {$value}");
        }
    }

    /**
     * Создать из строки.
     */
    public static function fromString(string $value): self
    {
        return new self(strtolower(trim($value)));
    }

    /**
     * Создать из файла.
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException("File not found or not readable: {$path}");
        }

        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new InvalidArgumentException("Failed to calculate checksum for: {$path}");
        }

        return new self($hash);
    }

    /**
     * Получить строковое представление.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Приведение к строке.
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Проверить валидность SHA256 checksum.
     */
    private function isValid(string $value): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    /**
     * Сравнить с другим checksum.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
