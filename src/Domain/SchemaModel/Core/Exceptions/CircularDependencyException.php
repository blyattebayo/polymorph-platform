<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Exceptions;

use LogicException;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;

/**
 * Исключение при обнаружении циклической зависимости в иерархии полей.
 */
class CircularDependencyException extends LogicException implements ErrorConvertible
{
    public function __construct(
        private readonly int $fieldId,
        private readonly int $parentId,
    ) {
        parent::__construct(
            "Обнаружена циклическая зависимость: поле #{$fieldId} не может иметь ".
            "родителем #{$parentId}, так как это создаст цикл в иерархии."
        );
    }

    public static function create(int $fieldId, int $parentId): self
    {
        return new self($fieldId, $parentId);
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::VALIDATION_ERROR)
            ->detail($this->getMessage())
            ->meta([
                'field_id' => $this->fieldId,
                'parent_id' => $this->parentId,
            ])
            ->build();
    }
}
