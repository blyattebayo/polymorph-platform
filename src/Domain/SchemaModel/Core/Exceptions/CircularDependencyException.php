<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Exceptions;

use LogicException;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

/**
 * Исключение при обнаружении циклической зависимости в иерархии полей.
 */
class CircularDependencyException extends LogicException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        private readonly int $fieldId,
        private readonly int $parentId,
    ) {
        parent::__construct(
            "Circular dependency detected: field #{$fieldId} cannot have ".
            "#{$parentId} as its parent because that would create a cycle in the hierarchy."
        );
    }

    public static function create(int $fieldId, int $parentId): self
    {
        return new self($fieldId, $parentId);
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::VALIDATION_ERROR;
    }

    public function errorMeta(): array
    {
        return [
            'field_id' => $this->fieldId,
            'parent_id' => $this->parentId,
        ];
    }
}
