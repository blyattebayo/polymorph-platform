<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\ValueObjects;

use Polymorph\Platform\Domain\Users\Core\Exceptions\InvalidEmailException;
use Polymorph\Platform\Support\Validation\ValidationConstraints;

/**
 * Value Object для email адреса пользователя.
 *
 * Обеспечивает валидацию и нормализацию email по RFC 5322.
 */
final readonly class Email
{
    private function __construct(
        private string $value
    ) {
        if (! $this->isValid($value)) {
            throw new InvalidEmailException($value);
        }
    }

    /**
     * Создать из строки с нормализацией.
     */
    public static function fromString(string $value): self
    {
        $normalized = ValidationConstraints::email()->normalize($value);

        return new self($normalized);
    }

    /**
     * Получить строковое представление.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Проверить валидность email.
     */
    private function isValid(string $email): bool
    {
        return ValidationConstraints::email()->isValid($email);
    }
}
