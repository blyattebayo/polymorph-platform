<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\AST;

/**
 * Expression node (content inside {{ }})
 */
readonly class ExpressionNode implements ASTNode
{
    /**
     * @param PathNode $path
     * @param FilterNode[] $filters
     */
    public function __construct(
        public PathNode $path,
        public array $filters,
        public int $start,
        public int $end
    ) {
    }

    public function getSpan(): array
    {
        return [$this->start, $this->end];
    }
}

