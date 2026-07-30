<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

final class ExtensionException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        string $message,
        private readonly ErrorCode $errorCode = ErrorCode::INVALID_PLUGIN_MANIFEST,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): ErrorCode
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return ['resource' => 'plugin'];
    }
}
