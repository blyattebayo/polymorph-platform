<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\ValueObjects;

use Polymorph\Platform\Domain\Users\Core\Exceptions\InvalidPasswordException;
use Polymorph\Platform\Support\Validation\ValidationConstraints;

/**
 * Value Object для пароля пользователя.
 *
 * Обеспечивает валидацию силы пароля.
 */
final readonly class Password
{
    private function __construct(
        private string $value
    ) {}

    /**
     * Создать из открытого пароля с валидацией.
     */
    public static function fromPlain(string $plainPassword): self
    {
        $constraint = ValidationConstraints::password();
        $length = mb_strlen($plainPassword);

        if ($length < $constraint->min()) {
            throw InvalidPasswordException::tooShort($constraint->min());
        }

        if ($length > $constraint->max()) {
            throw InvalidPasswordException::tooLong($constraint->max());
        }

        return new self($plainPassword);
    }
}
