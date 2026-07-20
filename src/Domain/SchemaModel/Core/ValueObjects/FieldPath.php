<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects;

use InvalidArgumentException;
use Polymorph\Platform\Support\Validation\ValidationConstraints;

/**
 * Value Object для материализованного пути поля.
 *
 * Инкапсулирует логику работы с путями: построение, разбор, валидацию.
 * Неизменяемый объект.
 */
final readonly class FieldPath
{
    private const SEPARATOR = '.';

    private function __construct(
        private string $value
    ) {
        $this->validate();
    }

    /**
     * Создать из строки пути.
     */
    public static function fromString(string $path): self
    {
        return new self($path);
    }

    /**
     * Создать из имени и родительского пути.
     */
    public static function fromNameAndParent(string $name, ?self $parent): self
    {
        if ($parent === null) {
            return new self($name);
        }

        return new self($parent->value.self::SEPARATOR.$name);
    }

    /**
     * Получить строковое представление.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Получить сегменты пути.
     *
     * @return string[]
     */
    public function segments(): array
    {
        return explode(self::SEPARATOR, $this->value);
    }

    /**
     * Получить глубину вложенности (количество сегментов).
     */
    public function depth(): int
    {
        return count($this->segments());
    }

    /**
     * Проверить, является ли потомком другого пути.
     */
    public function isChildOf(self $other): bool
    {
        return str_starts_with($this->value, $other->value.self::SEPARATOR);
    }

    /**
     * Валидация пути.
     */
    private function validate(): void
    {
        if (empty($this->value)) {
            throw new InvalidArgumentException('Field path cannot be empty');
        }

        $segments = $this->segments();

        foreach ($segments as $segment) {
            if (preg_match('/^__[a-z][a-z0-9_]*$/', $segment)) {
                continue;
            }

            if (! ValidationConstraints::slug()->matches($segment)) {
                throw new InvalidArgumentException(
                    "Invalid field name segment: '{$segment}'. ".
                    'Must start with lowercase letter and contain only lowercase letters, digits, underscores and hyphens.'
                );
            }
        }

        if (count($segments) > 10) {
            throw new InvalidArgumentException('Field path is too deep (max 10 levels)');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
