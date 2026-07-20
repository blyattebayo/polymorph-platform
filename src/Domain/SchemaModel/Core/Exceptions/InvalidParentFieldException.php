<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Exceptions;

use LogicException;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;

/**
 * Исключение при указании некорректного родительского поля.
 */
class InvalidParentFieldException extends LogicException implements ErrorConvertible
{
    public function __construct(
        private readonly int $parentId,
        private readonly string $reason,
    ) {
        parent::__construct(
            "Невозможно использовать поле #{$parentId} как родительское: {$reason}"
        );
    }

    public static function notFound(int $parentId): self
    {
        return new self($parentId, 'поле не найдено');
    }

    public static function wrongSchema(int $parentId, string $expectedSchema, string $actualSchema): self
    {
        return new self(
            $parentId,
            "поле принадлежит схеме '{$actualSchema}', а ожидается '{$expectedSchema}'"
        );
    }

    public static function notContainer(int $parentId, string $type): self
    {
        return new self(
            $parentId,
            "поле типа '{$type}' не может содержать дочерние поля"
        );
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::VALIDATION_ERROR)
            ->detail($this->getMessage())
            ->meta([
                'parent_id' => $this->parentId,
                'reason' => $this->reason,
            ])
            ->build();
    }
}
