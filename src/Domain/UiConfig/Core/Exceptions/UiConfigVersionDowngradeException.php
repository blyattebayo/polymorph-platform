<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

final class UiConfigVersionDowngradeException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        private readonly int $storedVersion,
        private readonly int $submittedVersion,
    ) {
        parent::__construct('A newer UI config format is already stored.');
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }

    /** @return array<string, int> */
    public function errorMeta(): array
    {
        return [
            'stored_version' => $this->storedVersion,
            'submitted_version' => $this->submittedVersion,
        ];
    }
}
