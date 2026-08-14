<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

final class UniqueValueConflict extends \RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        public readonly string $fieldId,
        public readonly mixed $value,
        ?\Throwable $previous = null,
    ) {
        parent::__construct('The value is already in use for a unique field.', previous: $previous);
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }

    /** @return array<string,mixed> */
    public function errorMeta(): array
    {
        return [
            'reason' => 'unique_value_conflict',
            'field_id' => $this->fieldId,
            'value' => $this->value,
        ];
    }
}
