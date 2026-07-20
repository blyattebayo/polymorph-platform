<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\AST;

/**
 * Wildcard [*] node
 */
readonly class WildcardNode implements ASTNode
{
    public function __construct(
        public int $start,
        public int $end
    ) {}

    public function getSpan(): array
    {
        return [$this->start, $this->end];
    }
}
