<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Exceptions;

use LogicException;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

/**
 * Исключение при попытке удалить схему, которая используется в RecordDefinition.
 */
class SchemaInUseException extends LogicException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        private readonly string $schemaCode,
        private readonly int $usageCount,
    ) {
        parent::__construct(
            "Cannot delete schema '{$schemaCode}': it is used by {$usageCount} record definition(s). ".
            'Delete or reassign the related record definitions first.'
        );
    }

    public static function create(string $schemaCode, int $usageCount): self
    {
        return new self($schemaCode, $usageCount);
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return [
            'schema_code' => $this->schemaCode,
            'usage_count' => $this->usageCount,
        ];
    }
}
