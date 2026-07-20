<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\AST;

/**
 * Root AST node interface
 */
interface ASTNode
{
    /**
     * Get span in original source
     * [start position (inclusive), end position (exclusive)]
     */
    public function getSpan(): array;
}
