<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\AST;

/**
 * ref(fieldId) node
 */
readonly class RefNode implements ASTNode
{
    public function __construct(
        public int $fieldId,
        public int $start,
        public int $end
    ) {
    }

    public function getSpan(): array
    {
        return [$this->start, $this->end];
    }
}

