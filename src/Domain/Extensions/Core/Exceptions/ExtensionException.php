<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use RuntimeException;

final class ExtensionException extends RuntimeException implements ErrorConvertible
{
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

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for($this->errorCode)
            ->detail($this->getMessage())
            ->meta(['resource' => 'plugin'])
            ->build();
    }
}
