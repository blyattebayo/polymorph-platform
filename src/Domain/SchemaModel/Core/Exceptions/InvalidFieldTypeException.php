<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Exceptions;

use InvalidArgumentException;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;

/**
 * Исключение при указании недопустимого типа поля.
 */
class InvalidFieldTypeException extends InvalidArgumentException implements ErrorConvertible
{
    public function __construct(
        private readonly string $invalidType,
        private readonly array $validTypes,
    ) {
        parent::__construct(
            "Недопустимый тип поля '{$invalidType}'. ".
            'Допустимые типы: '.implode(', ', $validTypes)
        );
    }

    public static function create(string $invalidType, array $validTypes): self
    {
        return new self($invalidType, $validTypes);
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::VALIDATION_ERROR)
            ->detail($this->getMessage())
            ->meta([
                'invalid_type' => $this->invalidType,
                'valid_types' => $this->validTypes,
            ])
            ->build();
    }
}
