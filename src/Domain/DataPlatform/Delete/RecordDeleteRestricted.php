<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Delete;

use Polymorph\Platform\Domain\DataPlatform\Errors\DescribesActiveReferenceConflict;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;

final class RecordDeleteRestricted extends \RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;
    use DescribesActiveReferenceConflict;

    /** @param list<array{source_record_id:int,field_id:string}> $references */
    public function __construct(
        public readonly int $recordId,
        public readonly array $references,
        public readonly int $hiddenReferenceCount = 0,
    ) {
        parent::__construct("Record {$recordId} is referenced by active records using the restrict policy.");
    }

    protected function referenceConflictReason(): string
    {
        return 'record_delete_restricted';
    }

    protected function referenceIdentityMeta(): array
    {
        return ['record_id' => $this->recordId];
    }
}
