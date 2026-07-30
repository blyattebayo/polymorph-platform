<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Exceptions;

use LogicException;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

/**
 * Исключение при указании некорректного родительского поля.
 */
class InvalidParentFieldException extends LogicException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        private readonly int $parentId,
        private readonly string $reason,
    ) {
        parent::__construct(
            "Cannot use field #{$parentId} as parent: {$reason}"
        );
    }

    public static function notFound(int $parentId): self
    {
        return new self($parentId, 'field not found');
    }

    public static function wrongSchema(int $parentId, string $expectedSchema, string $actualSchema): self
    {
        return new self(
            $parentId,
            "field belongs to schema '{$actualSchema}', expected '{$expectedSchema}'"
        );
    }

    public static function notContainer(int $parentId, string $type): self
    {
        return new self(
            $parentId,
            "field of type '{$type}' cannot contain child fields"
        );
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::VALIDATION_ERROR;
    }

    public function errorMeta(): array
    {
        return [
            'parent_id' => $this->parentId,
            'reason' => $this->reason,
        ];
    }
}
