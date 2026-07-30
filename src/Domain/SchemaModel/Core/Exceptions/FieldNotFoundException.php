<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

/**
 * Исключение при ненахождении поля.
 */
class FieldNotFoundException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        private readonly int|string $identifier,
        private readonly string $identifierType = 'id',
    ) {
        parent::__construct(
            "Field not found: {$identifierType} = {$identifier}"
        );
    }

    public static function byId(int $id): self
    {
        return new self($id, 'id');
    }

    public static function byPath(string $path): self
    {
        return new self($path, 'path');
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::NOT_FOUND;
    }

    protected function errorTitle(): ?string
    {
        return 'Field not found';
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return [
            'resource' => 'field',
            'identifier' => $this->identifier,
            'identifier_type' => $this->identifierType,
        ];
    }
}
