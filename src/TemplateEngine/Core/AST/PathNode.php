<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\AST;

/**
 * Path node (ref().field().field()[*])
 */
readonly class PathNode implements ASTNode
{
    /**
     * @param  array<RefNode|FieldNode|WildcardNode>  $segments
     */
    public function __construct(
        public RefNode|FieldNode $head,
        public array $segments,
        public int $start,
        public int $end
    ) {}

    public function getSpan(): array
    {
        return [$this->start, $this->end];
    }
}
