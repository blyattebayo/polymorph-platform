<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

final class PersonalAccessTokenAccessDenied extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    private function __construct(string $message, private readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function administrativeCapabilityRequired(string $action): self
    {
        return new self(
            'Administrative personal access token authority is required.',
            'administrative_'.$action.'_required',
        );
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::FORBIDDEN;
    }

    /** @return array{reason: string} */
    public function errorMeta(): array
    {
        return ['reason' => $this->reason];
    }
}
