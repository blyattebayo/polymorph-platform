<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Errors;

use InvalidArgumentException;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

final class DataPlatformResourceNotFound extends InvalidArgumentException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    private function __construct(
        public readonly string $resource,
        public readonly int|string $resourceId,
    ) {
        parent::__construct(ucfirst(str_replace('-', ' ', $resource))." {$resourceId} does not exist.");
    }

    public static function for(string $resource, int|string $resourceId): self
    {
        return new self($resource, $resourceId);
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::NOT_FOUND;
    }

    /** @return array<string, mixed> */
    public function errorMeta(): array
    {
        return [
            'reason' => 'resource_not_found',
            'resource' => $this->resource,
            'resource_id' => $this->resourceId,
        ];
    }
}
