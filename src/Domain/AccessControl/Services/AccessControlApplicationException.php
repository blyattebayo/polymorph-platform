<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use RuntimeException;

final class AccessControlApplicationException extends RuntimeException implements ErrorConvertible
{
    private function __construct(string $message, private readonly string $kind)
    {
        parent::__construct($message);
    }

    public static function validation(string $message): self
    {
        return new self($message, 'validation');
    }

    public static function notFound(string $message): self
    {
        return new self($message, 'not_found');
    }

    public static function conflict(string $message): self
    {
        return new self($message, 'conflict');
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        $code = match ($this->kind) {
            'not_found' => ErrorCode::NOT_FOUND,
            'conflict' => ErrorCode::CONFLICT,
            default => ErrorCode::VALIDATION_ERROR,
        };

        return $factory->for($code)->detail($this->getMessage())->build();
    }
}
