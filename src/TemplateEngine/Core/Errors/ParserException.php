<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\Errors;

use Polymorph\Platform\TemplateEngine\Core\Lexer\Token;
use RuntimeException;

/**
 * Parser exception with token information
 */
class ParserException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?Token $token = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
