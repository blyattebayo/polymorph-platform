<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Errors;

use Polymorph\Platform\Support\Errors\ErrorCode;

/** Shared public contract for conflicts caused by active record references. */
trait DescribesActiveReferenceConflict
{
    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }

    public function errorMeta(): array
    {
        return [
            'reason' => $this->referenceConflictReason(),
            ...$this->referenceIdentityMeta(),
            'reference_count' => count($this->references) + $this->hiddenReferenceCount,
            'references' => $this->references,
            'hidden_reference_count' => $this->hiddenReferenceCount,
        ];
    }

    abstract protected function referenceConflictReason(): string;

    /** @return array<string,int|string> */
    abstract protected function referenceIdentityMeta(): array;
}
