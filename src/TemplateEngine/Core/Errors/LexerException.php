<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\Errors;

use RuntimeException;

/**
 * Lexer exception with position information
 */
class LexerException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $position,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
