<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\AST;

/**
 * Plain text node (outside {{ }})
 */
readonly class TextNode implements ASTNode
{
    public function __construct(
        public string $text,
        public int $start,
        public int $end
    ) {
    }

    public function getSpan(): array
    {
        return [$this->start, $this->end];
    }
}

