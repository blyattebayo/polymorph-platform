<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\Lexer;

/**
 * Template token
 */
final readonly class Token
{
    public function __construct(
        public TokenType $type,
        public string $value,
        public int $position,
        public int $length,
    ) {}

    public function is(TokenType $type): bool
    {
        return $this->type === $type;
    }
}
