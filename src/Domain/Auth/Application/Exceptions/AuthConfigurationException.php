<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DescribesErrorReport;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorReport;
use RuntimeException;

final class AuthConfigurationException extends RuntimeException implements DescribesErrorReport, DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public static function missingSessionTokenSigningKey(): self
    {
        return new self('Session token signing key is not configured.');
    }

    public static function invalid(string $reason): self
    {
        return new self('Invalid Auth configuration: '.trim($reason));
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::INTERNAL_SERVER_ERROR;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return [];
    }

    protected function errorDetail(): string
    {
        return 'Service configuration error';
    }

    public function errorReport(ErrorPayload $payload): ErrorReport
    {
        return new ErrorReport(
            level: 'critical',
            message: 'Auth configuration error detected',
            context: [
                'reason' => $this->getMessage(),
                'suggestion' => 'Check the authentication environment and configuration',
            ],
        );
    }
}
