<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\Errors;

use RuntimeException;

/**
 * Validation exception with field path information
 */
class ValidationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $fieldPath = '',
        public readonly ?int $spanStart = null,
        public readonly ?int $spanEnd = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}

