<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Media;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

final class MediaHasActiveReferences extends \RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    /** @param list<array{source_record_id:int,field_id:string}> $references */
    public function __construct(public readonly string $mediaId, public readonly array $references)
    {
        parent::__construct("Media {$mediaId} is referenced by active records and cannot be physically deleted.");
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }

    public function errorMeta(): array
    {
        return [
            'reason' => 'media_has_active_references',
            'media_id' => $this->mediaId,
            'references' => $this->references,
        ];
    }
}
