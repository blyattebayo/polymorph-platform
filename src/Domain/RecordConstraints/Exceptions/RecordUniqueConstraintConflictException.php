<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordConstraints\Exceptions;

use Illuminate\Database\QueryException;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

final class RecordUniqueConstraintConflictException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        private readonly int $recordDefinitionId,
        private readonly string $fieldPath,
        QueryException $previous,
    ) {
        parent::__construct(
            "Field '{$fieldPath}' contains duplicate active values and cannot be made unique.",
            0,
            $previous,
        );
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }

    public function errorMeta(): array
    {
        return [
            'record_definition_id' => $this->recordDefinitionId,
            'field_path' => $this->fieldPath,
        ];
    }
}
