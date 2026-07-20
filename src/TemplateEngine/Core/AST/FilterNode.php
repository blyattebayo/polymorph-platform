<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\AST;

/**
 * Filter node: | filterName(arg1, arg2)
 */
readonly class FilterNode implements ASTNode
{
    /**
     * @param  array<int|string>  $args
     */
    public function __construct(
        public string $name,
        public array $args,
        public int $start,
        public int $end
    ) {}

    public function getSpan(): array
    {
        return [$this->start, $this->end];
    }
}
