<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Media;

use Polymorph\Platform\Domain\DataPlatform\Errors\DescribesActiveReferenceConflict;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;

final class MediaHasActiveReferences extends \RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;
    use DescribesActiveReferenceConflict;

    /** @param list<array{source_record_id:int,field_id:string}> $references */
    public function __construct(
        public readonly string $mediaId,
        public readonly array $references,
        public readonly int $hiddenReferenceCount = 0,
    ) {
        parent::__construct("Media {$mediaId} is referenced by active records and cannot be physically deleted.");
    }

    protected function referenceConflictReason(): string
    {
        return 'media_has_active_references';
    }

    protected function referenceIdentityMeta(): array
    {
        return ['media_id' => $this->mediaId];
    }
}
