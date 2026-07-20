<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use RuntimeException;

/**
 * Исключение при ненахождении схемы.
 */
class SchemaNotFoundException extends RuntimeException implements ErrorConvertible
{
    public function __construct(
        private readonly int|string $identifier,
        private readonly string $identifierType = 'id',
    ) {
        parent::__construct(
            "Схема не найдена: {$identifierType} = {$identifier}"
        );
    }

    public static function byId(int $id): self
    {
        return new self($id, 'id');
    }

    public static function byCode(string $code): self
    {
        return new self($code, 'code');
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::NOT_FOUND)
            ->detail($this->getMessage())
            ->meta([
                'identifier' => $this->identifier,
                'identifier_type' => $this->identifierType,
            ])
            ->build();
    }
}
