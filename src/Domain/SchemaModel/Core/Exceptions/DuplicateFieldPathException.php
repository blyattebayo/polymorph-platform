<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

/**
 * Исключение при попытке создать поле с дублирующимся путем.
 */
class DuplicateFieldPathException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        private readonly string $fullPath,
        private readonly string $schemaCode,
    ) {
        parent::__construct(
            "Path '{$fullPath}' already exists in schema '{$schemaCode}'. ".
            'Use a different field name or delete the existing path.'
        );
    }

    public static function create(string $fullPath, string $schemaCode): self
    {
        return new self($fullPath, $schemaCode);
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }

    public function errorMeta(): array
    {
        return [
            'full_path' => $this->fullPath,
            'schema_code' => $this->schemaCode,
        ];
    }
}
