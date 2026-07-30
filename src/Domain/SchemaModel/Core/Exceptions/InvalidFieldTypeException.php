<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Exceptions;

use InvalidArgumentException;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

/**
 * Исключение при указании недопустимого типа поля.
 */
class InvalidFieldTypeException extends InvalidArgumentException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        private readonly string $invalidType,
        private readonly array $validTypes,
    ) {
        parent::__construct(
            "Invalid field type '{$invalidType}'. ".
            'Valid types: '.implode(', ', $validTypes)
        );
    }

    public static function create(string $invalidType, array $validTypes): self
    {
        return new self($invalidType, $validTypes);
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
        return [
            'invalid_type' => $this->invalidType,
            'valid_types' => $this->validTypes,
        ];
    }
}
