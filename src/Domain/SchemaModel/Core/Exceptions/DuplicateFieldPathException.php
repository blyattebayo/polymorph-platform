<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use RuntimeException;

/**
 * Исключение при попытке создать поле с дублирующимся путем.
 */
class DuplicateFieldPathException extends RuntimeException implements ErrorConvertible
{
    public function __construct(
        private readonly string $fullPath,
        private readonly string $schemaCode,
    ) {
        parent::__construct(
            "Путь '{$fullPath}' уже существует в схеме '{$schemaCode}'. " .
            "Используйте другое имя поля или удалите существующий путь."
        );
    }

    public static function create(string $fullPath, string $schemaCode): self
    {
        return new self($fullPath, $schemaCode);
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::CONFLICT)
            ->detail($this->getMessage())
            ->meta([
                'full_path' => $this->fullPath,
                'schema_code' => $this->schemaCode,
            ])
            ->build();
    }
}
