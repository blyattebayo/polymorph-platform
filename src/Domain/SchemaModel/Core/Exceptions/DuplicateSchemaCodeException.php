<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use RuntimeException;

/**
 * Исключение при попытке создать схему с дублирующимся кодом.
 */
class DuplicateSchemaCodeException extends RuntimeException implements ErrorConvertible
{
    public function __construct(
        private readonly string $code,
    ) {
        parent::__construct(
            "Схема с кодом '{$code}' уже существует. Используйте другой уникальный код."
        );
    }

    public static function create(string $code): self
    {
        return new self($code);
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::CONFLICT)
            ->detail($this->getMessage())
            ->meta([
                'code' => $this->code,
            ])
            ->build();
    }
}
