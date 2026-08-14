<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Write;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

final class OptimisticLockConflict extends \RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        public readonly int $recordId,
        public readonly int $expectedRevision,
        public readonly int $actualRevision,
    ) {
        parent::__construct(
            "Record {$recordId} revision conflict: expected {$expectedRevision}, actual {$actualRevision}.",
        );
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }

    public function errorMeta(): array
    {
        return [
            'reason' => 'optimistic_lock_conflict',
            'record_id' => $this->recordId,
            'expected_revision' => $this->expectedRevision,
            'actual_revision' => $this->actualRevision,
        ];
    }
}
