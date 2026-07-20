<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Exceptions;

use InvalidArgumentException;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;

/**
 * Исключение при нарушении constraints.
 */
class ConstraintViolationException extends InvalidArgumentException implements ErrorConvertible
{
    public function __construct(
        private readonly string $fieldName,
        private readonly string $constraintType,
        private readonly string $reason,
    ) {
        parent::__construct(
            "Нарушение constraint для поля '{$fieldName}' (тип: {$constraintType}): {$reason}"
        );
    }

    public static function create(string $fieldName, string $constraintType, string $reason): self
    {
        return new self($fieldName, $constraintType, $reason);
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::VALIDATION_ERROR)
            ->detail($this->getMessage())
            ->meta([
                'field_name' => $this->fieldName,
                'constraint_type' => $this->constraintType,
                'reason' => $this->reason,
            ])
            ->build();
    }
}
