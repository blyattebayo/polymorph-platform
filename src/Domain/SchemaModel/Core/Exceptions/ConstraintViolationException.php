<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Exceptions;

use InvalidArgumentException;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

/**
 * Исключение при нарушении constraints.
 */
class ConstraintViolationException extends InvalidArgumentException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        private readonly string $fieldName,
        private readonly string $constraintType,
        private readonly string $reason,
    ) {
        parent::__construct(
            "Constraint violation for field '{$fieldName}' (type: {$constraintType}): {$reason}"
        );
    }

    public static function create(string $fieldName, string $constraintType, string $reason): self
    {
        return new self($fieldName, $constraintType, $reason);
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
            'field_name' => $this->fieldName,
            'constraint_type' => $this->constraintType,
            'reason' => $this->reason,
        ];
    }
}
