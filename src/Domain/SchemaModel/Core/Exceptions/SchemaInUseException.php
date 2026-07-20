<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use LogicException;

/**
 * Исключение при попытке удалить схему, которая используется в RecordDefinition.
 */
class SchemaInUseException extends LogicException implements ErrorConvertible
{
    public function __construct(
        private readonly string $schemaCode,
        private readonly int $usageCount,
    ) {
        parent::__construct(
            "Невозможно удалить схему '{$schemaCode}': она используется в {$usageCount} типах записей. " .
            "Сначала удалите или переназначьте связанные RecordDefinition."
        );
    }

    public static function create(string $schemaCode, int $usageCount): self
    {
        return new self($schemaCode, $usageCount);
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::CONFLICT)
            ->detail($this->getMessage())
            ->meta([
                'schema_code' => $this->schemaCode,
                'usage_count' => $this->usageCount,
            ])
            ->build();
    }
}
