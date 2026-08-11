<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

final class PersonalAccessTokenNotFound extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    private function __construct(string $message, private readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function token(): self
    {
        return new self('Personal access token was not found.', 'pat_not_found');
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::NOT_FOUND;
    }

    /** @return array{reason: string} */
    public function errorMeta(): array
    {
        return ['reason' => $this->reason];
    }
}
