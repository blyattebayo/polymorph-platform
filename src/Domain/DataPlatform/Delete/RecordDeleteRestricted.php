<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Delete;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

final class RecordDeleteRestricted extends \RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    /** @param list<array{source_record_id:int,field_id:string}> $references */
    public function __construct(public readonly int $recordId, public readonly array $references)
    {
        parent::__construct("Record {$recordId} is referenced by active records using the restrict policy.");
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }

    public function errorMeta(): array
    {
        return [
            'reason' => 'record_delete_restricted',
            'record_id' => $this->recordId,
            'references' => $this->references,
        ];
    }
}
