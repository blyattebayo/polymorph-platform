<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\Parser;

use Polymorph\Platform\TemplateEngine\Core\AST\ExpressionNode;
use Polymorph\Platform\TemplateEngine\Core\AST\FieldNode;
use Polymorph\Platform\TemplateEngine\Core\AST\FilterNode;
use Polymorph\Platform\TemplateEngine\Core\AST\PathNode;
use Polymorph\Platform\TemplateEngine\Core\AST\RefNode;
use Polymorph\Platform\TemplateEngine\Core\AST\TemplateNode;
use Polymorph\Platform\TemplateEngine\Core\AST\TextNode;
use Polymorph\Platform\TemplateEngine\Core\AST\WildcardNode;
use Polymorph\Platform\TemplateEngine\Core\Errors\ParserException;
use Polymorph\Platform\TemplateEngine\Core\Lexer\Token;
use Polymorph\Platform\TemplateEngine\Core\Lexer\TokenType;

/**
 * Parser for template engine syntax.
 * Converts a token stream to an abstract syntax tree (AST).
 *
 * GRAMMAR:
 *
 * template     → (text | expression)*
 * expression   → '{{' path filters? '}}'
 * path         → accessor segments*
 * ref_accessor → 'ref' '(' INTEGER ')'
 * field_accessor → 'field' '(' INTEGER ')'
 * segments     → (path_segment | wildcard)*
 * path_segment → '.' ('ref' | 'field') '(' INTEGER ')'
 * wildcard     → '[' '*' ']'
 * filters      → ('|' filter)+
 * filter       → IDENT ('(' args ')')?
 * args         → (STRING | INTEGER) (',' (STRING | INTEGER))*
 *
 * AST NODE TYPES:
 * - TemplateNode: Root node containing children
 * - TextNode: Plain text outside expressions
 * - ExpressionNode: Expression {{ ... }} with path and filters
 * - PathNode: Access path with head and segments
 * - RefNode: ref(fieldId) reference
 * - FieldNode: field(fieldId) access
 * - WildcardNode: [*] array expansion
 * - FilterNode: filter(args) transformation
 *
 * EXAMPLES:
 * {{ field(10) }}                          - Simple field access
 * {{ ref(100).field(200) }}                - Reference traversal to field
 * {{ field(10)[*] }}                       - Field with wildcard
 * {{ field(10) | uppercase }}              - Field with filter
 * {{ ref(1).field(2)[*] | join(', ') }}   - Complex expression
 *
 * ERROR HANDLING:
 * Throws ParserException on:
 * - Missing required tokens (parentheses, field IDs, etc.)
 * - Invalid token sequences
 * - Empty expressions
 * - Unexpected tokens
 *
 * @see Tests\Unit\TemplateEngine\TemplateParserTest for comprehensive test coverage
 */
class TemplateParser
{
    /** @var Token[] */
    private array $tokens;

    private int $position = 0;

    /**
     * Parse a token stream to an AST.
     *
     * @param  Token[]  $tokens
     *
     * @throws ParserException
     */
    public function parse(array $tokens): TemplateNode
    {
        $this->tokens = $tokens;
        $this->position = 0;

        $children = [];

        while (! $this->isAtEnd()) {
            $token = $this->current();

            if ($token->type === TokenType::TEXT) {
                $children[] = new TextNode(
                    $token->value,
                    $token->position,
                    $token->position + $token->length
                );
                $this->advance();
            } elseif ($token->type === TokenType::EXPR_START) {
                $children[] = $this->parseExpression();
            } else {
                throw new ParserException(
                    "Unexpected token at top level: {$token->type->value}",
                    $token
                );
            }
        }

        return new TemplateNode($children);
    }

    /**
     * Parse expression: {{ path | filter | filter }}
     */
    private function parseExpression(): ExpressionNode
    {
        $start = $this->expect(TokenType::EXPR_START)->position;

        $path = $this->parsePath();
        $filters = [];

        while ($this->match(TokenType::PIPE)) {
            $filters[] = $this->parseFilter();
        }

        $endToken = $this->expect(TokenType::EXPR_END);

        return new ExpressionNode(
            $path,
            $filters,
            $start,
            $endToken->position + $endToken->length
        );
    }

    /**
     * Parse path: ref(10).field(20)[*] or field(30)
     */
    private function parsePath(): PathNode
    {
        $startPos = $this->current()->position;

        // Parse head: ref(id) or field(id)
        $head = $this->parsePathHead();

        // Parse segments: .field(id), .ref(id), or [*]
        $segments = [];
        while ($this->match(TokenType::DOT) || $this->check(TokenType::LBRACKET)) {
            if ($this->check(TokenType::LBRACKET)) {
                $segments[] = $this->parseWildcard();
            } else {
                $segments[] = $this->parsePathSegment();
            }
        }

        $endPos = $this->previous()->position + $this->previous()->length;

        return new PathNode($head, $segments, $startPos, $endPos);
    }

    /**
     * Parse path head: ref(10) or field(10)
     */
    private function parsePathHead(): RefNode|FieldNode
    {
        $token = $this->current();
        $start = $token->position;

        if ($this->match(TokenType::REF)) {
            $this->expect(TokenType::LPAREN);
            $idToken = $this->expect(TokenType::INTEGER);
            $endToken = $this->expect(TokenType::RPAREN);

            return new RefNode(
                (int) $idToken->value,
                $start,
                $endToken->position + $endToken->length
            );
        }

        if ($this->match(TokenType::FIELD)) {
            $this->expect(TokenType::LPAREN);
            $idToken = $this->expect(TokenType::INTEGER);
            $endToken = $this->expect(TokenType::RPAREN);

            return new FieldNode(
                (int) $idToken->value,
                $start,
                $endToken->position + $endToken->length
            );
        }

        throw new ParserException(
            "Expected 'ref' or 'field' at start of path",
            $token
        );
    }

    /**
     * Parse path segment: field(10) or ref(10)
     */
    private function parsePathSegment(): RefNode|FieldNode
    {
        $token = $this->current();
        $start = $token->position;

        if ($this->match(TokenType::REF)) {
            $this->expect(TokenType::LPAREN);
            $idToken = $this->expect(TokenType::INTEGER);
            $endToken = $this->expect(TokenType::RPAREN);

            return new RefNode(
                (int) $idToken->value,
                $start,
                $endToken->position + $endToken->length
            );
        }

        if ($this->match(TokenType::FIELD)) {
            $this->expect(TokenType::LPAREN);
            $idToken = $this->expect(TokenType::INTEGER);
            $endToken = $this->expect(TokenType::RPAREN);

            return new FieldNode(
                (int) $idToken->value,
                $start,
                $endToken->position + $endToken->length
            );
        }

        throw new ParserException(
            "Expected 'ref' or 'field' in path segment",
            $token
        );
    }

    /**
     * Parse wildcard: [*]
     */
    private function parseWildcard(): WildcardNode
    {
        $start = $this->expect(TokenType::LBRACKET)->position;
        $this->expect(TokenType::WILDCARD);
        $endToken = $this->expect(TokenType::RBRACKET);

        return new WildcardNode(
            $start,
            $endToken->position + $endToken->length
        );
    }

    /**
     * Parse filter: filterName(arg1, arg2)
     */
    private function parseFilter(): FilterNode
    {
        $nameToken = $this->expect(TokenType::IDENT);
        $start = $nameToken->position;
        $args = [];

        if ($this->match(TokenType::LPAREN)) {
            if (! $this->check(TokenType::RPAREN)) {
                do {
                    $args[] = $this->parseFilterArg();
                } while ($this->match(TokenType::COMMA));
            }
            $endToken = $this->expect(TokenType::RPAREN);
        } else {
            $endToken = $nameToken;
        }

        return new FilterNode(
            $nameToken->value,
            $args,
            $start,
            $endToken->position + $endToken->length
        );
    }

    /**
     * Parse filter argument: integer or string
     */
    private function parseFilterArg(): int|string
    {
        if ($this->check(TokenType::INTEGER)) {
            $token = $this->advance();

            return (int) $token->value;
        }

        if ($this->check(TokenType::STRING)) {
            $token = $this->advance();

            return $token->value;
        }

        throw new ParserException(
            'Expected integer or string as filter argument',
            $this->current()
        );
    }

    // ===== Utility methods =====

    private function current(): Token
    {
        return $this->tokens[$this->position];
    }

    private function previous(): Token
    {
        return $this->tokens[$this->position - 1];
    }

    private function advance(): Token
    {
        if (! $this->isAtEnd()) {
            $this->position++;
        }

        return $this->previous();
    }

    private function isAtEnd(): bool
    {
        return $this->current()->type === TokenType::EOF;
    }

    private function check(TokenType $type): bool
    {
        if ($this->isAtEnd()) {
            return false;
        }

        return $this->current()->type === $type;
    }

    private function match(TokenType ...$types): bool
    {
        foreach ($types as $type) {
            if ($this->check($type)) {
                $this->advance();

                return true;
            }
        }

        return false;
    }

    private function expect(TokenType $type): Token
    {
        if ($this->check($type)) {
            return $this->advance();
        }

        $current = $this->current();
        throw new ParserException(
            "Expected {$type->value}, got {$current->type->value}",
            $current
        );
    }
}
