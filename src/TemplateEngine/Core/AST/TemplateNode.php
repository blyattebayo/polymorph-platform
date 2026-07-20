<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\AST;

/**
 * Root template node containing mixed text and expressions
 */
readonly class TemplateNode implements ASTNode
{
    /**
     * @param array<TextNode|ExpressionNode> $children
     */
    public function __construct(
        public array $children
    ) {
    }

    public function getSpan(): array
    {
        if (empty($this->children)) {
            return [0, 0];
        }

        return [
            $this->children[0]->getSpan()[0],
            $this->children[array_key_last($this->children)]->getSpan()[1]
        ];
    }
}

