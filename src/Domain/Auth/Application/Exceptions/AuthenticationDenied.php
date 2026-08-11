<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

final class AuthenticationDenied extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    private function __construct(
        private readonly string $reason,
        private readonly string $clientMessage,
    ) {
        parent::__construct($clientMessage);
    }

    public static function invalidCredentials(): self
    {
        return new self('invalid_credentials', 'Invalid credentials.');
    }

    public static function inactiveAccount(): self
    {
        return new self('inactive_user', 'Account is not active.');
    }

    public static function invalidAccessToken(): self
    {
        return new self('invalid_token', 'Access token is invalid.');
    }

    public static function ambiguousCredentials(): self
    {
        return new self('ambiguous_credentials', 'Send exactly one authentication credential.');
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::UNAUTHORIZED;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return [
            'reason' => $this->reason,
            'message' => $this->clientMessage,
        ];
    }

    protected function errorDetail(): string
    {
        return 'Authentication failed.';
    }
}
