<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\Pipeline;

use Polymorph\Platform\TemplateEngine\Core\AST\TemplateNode;
use Polymorph\Platform\TemplateEngine\Core\Lexer\TemplateLexer;
use Polymorph\Platform\TemplateEngine\Core\Parser\TemplateParser;
use Polymorph\Platform\TemplateEngine\Core\Validator\ASTValidator;

final readonly class TemplateParsePipeline
{
    public function __construct(
        private TemplateLexer $lexer,
        private TemplateParser $parser,
        private ASTValidator $validator,
    ) {}

    public function parseAndValidate(string $template): TemplateNode
    {
        $tokens = $this->lexer->tokenize($template);
        $ast = $this->parser->parse($tokens);
        $this->validator->validate($ast);

        return $ast;
    }
}
