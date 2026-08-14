<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Access;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

final class DataAccessDenied extends \RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    private function __construct(
        public readonly string $resource,
        public readonly string $action,
    ) {
        parent::__construct("Access denied for {$action} on {$resource}.");
    }

    public static function for(string $resource, string $action): self
    {
        return new self($resource, $action);
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::FORBIDDEN;
    }

    public function errorMeta(): array
    {
        return ['reason' => 'data_access_denied', 'action' => $this->action];
    }

    protected function errorDetail(): string
    {
        return 'The requested data operation is not permitted.';
    }
}
