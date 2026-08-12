<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Exceptions;

use LogicException;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\SchemaUsageInfo;

/**
 * Исключение при попытке удалить схему, которая используется в RecordDefinition.
 */
class SchemaInUseException extends LogicException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        private readonly SchemaUsageInfo $usage,
    ) {
        parent::__construct(
            "Cannot delete schema '{$usage->schemaCode}': it is used by {$usage->usageCount()} record definition(s). ".
            'Delete or reassign the related record definitions first.'
        );
    }

    public static function create(SchemaUsageInfo $usage): self
    {
        return new self($usage);
    }

    public function usage(): SchemaUsageInfo
    {
        return $this->usage;
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
        return $this->usage->toConflictMeta();
    }
}
