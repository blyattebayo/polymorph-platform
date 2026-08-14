<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Errors;

use LogicException;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

final class DataPlatformPreconditionRequired extends LogicException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    private function __construct()
    {
        parent::__construct('expected_revision or If-Match is required.');
    }

    public static function required(): self
    {
        return new self;
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::PRECONDITION_REQUIRED;
    }

    /** @return array{reason:string} */
    public function errorMeta(): array
    {
        return ['reason' => 'record_revision_precondition_required'];
    }
}
