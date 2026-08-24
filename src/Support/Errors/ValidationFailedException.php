<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use RuntimeException;

/**
 * Операция не прошла проверку.
 *
 * Единственное место, где описано, как выглядит ошибка валидации: код, текст и
 * `meta.errors`. Форма публичная и не должна зависеть от того, кто проверял —
 * FormRequest на транспорте или валидатор в домене: до этого класса та же сборка
 * payload'а стояла в обоих, и разъехаться они могли молча.
 *
 * Проверяющему при этом не нужен ни ErrorFactory, ни знание про HTTP: он бросает
 * исключение с данными, а ответ собирает {@see ConvertsToErrorPayload}.
 */
final class ValidationFailedException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    /**
     * @param  array<string, list<string>>  $errors  сообщения об ошибках по полям
     */
    public function __construct(
        private readonly array $errors,
        string $detail = 'Validation failed.',
    ) {
        parent::__construct($detail);
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::VALIDATION_ERROR;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return ['errors' => $this->errors];
    }
}
