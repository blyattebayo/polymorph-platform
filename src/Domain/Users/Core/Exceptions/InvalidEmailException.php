<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use RuntimeException;

/**
 * Исключение: невалидный email адрес.
 */
class InvalidEmailException extends RuntimeException implements ErrorConvertible
{
    public function __construct(
        public readonly string $email,
        public readonly string $reason = 'Invalid email format'
    ) {
        parent::__construct("Invalid email address: {$email}");
    }

    /**
     * Конвертировать в ErrorPayload для API.
     */
    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::VALIDATION_ERROR)
            ->detail($this->reason)
            ->meta([
                'field' => 'email',
                'value' => $this->email,
                'reason' => 'invalid_format',
            ])
            ->build();
    }
}