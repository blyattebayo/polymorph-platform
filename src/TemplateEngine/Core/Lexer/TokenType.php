<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\Lexer;

/**
 * Token types for template engine lexer
 */
enum TokenType: string
{
    case TEXT = 'TEXT';
    case EXPR_START = 'EXPR_START';     // {{
    case EXPR_END = 'EXPR_END';         // }}
    case REF = 'REF';                    // ref
    case FIELD = 'FIELD';                // field
    case LPAREN = 'LPAREN';              // (
    case RPAREN = 'RPAREN';              // )
    case DOT = 'DOT';                    // .
    case LBRACKET = 'LBRACKET';          // [
    case RBRACKET = 'RBRACKET';          // ]
    case WILDCARD = 'WILDCARD';          // *
    case PIPE = 'PIPE';                  // |
    case INTEGER = 'INTEGER';            // 123
    case IDENT = 'IDENT';                // identifier
    case STRING = 'STRING';              // 'string'
    case COMMA = 'COMMA';                // ,
    case EOF = 'EOF';
}
