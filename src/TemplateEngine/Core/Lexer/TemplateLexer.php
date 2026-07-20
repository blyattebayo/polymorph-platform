<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\Lexer;

use Polymorph\Platform\TemplateEngine\Core\Errors\LexerException;

/**
 * Lexer for the template engine.
 * 
 * Tokenizes ID-based template syntax:
 * {{ ref(10).field(20)[*] | filter(arg) }}
 * 
 * SUPPORTED FEATURES:
 * - Text outside expressions
 * - Expressions: {{ ... }}
 * - Keywords: ref, field
 * - Identifiers: a-z, A-Z, 0-9, _ (must start with letter or underscore)
 * - Integers: 0-9+ (positive only)
 * - Strings: 'single quotes only' with \' and \\ escapes
 * - Operators: ( ) . [ ] * | ,
 * 
 * DOCUMENTED LIMITATIONS:
 * ✗ No negative numbers (no minus operator)
 * ✗ No float numbers (10.5 tokenizes as INTEGER DOT INTEGER)
 * ✗ No double-quoted strings (only single quotes)
 * ✗ No unicode identifiers (ASCII only, ctype_alpha limitation)
 * ✗ No nested expressions ({{ {{ not allowed)
 * ✗ No arithmetic operators (+, -, *, /, %)
 * ✗ No comparison operators (==, !=, <, >, <=, >=)
 * ✗ No logical operators (!, &&, ||)
 * ✗ No ternary operator (? :)
 * ✗ No semicolons or other statement terminators
 * ✗ Limited escape sequences (only \' and \\, no \n, \t, \x, \u)
 * 
 * @see Tests\Unit\TemplateEngine\TemplateLexerTest for comprehensive test coverage
 */
final class TemplateLexer
{
    private string $source;
    private int $position = 0;
    private int $length = 0;
    
    /**
     * Tokenize template source.
     * 
     * @param string $source Template source code
     * @return Token[]
     * @throws LexerException
     */
    public function tokenize(string $source): array
    {
        $this->source = $source;
        $this->position = 0;
        $this->length = strlen($source);
        
        $tokens = [];
        
        while ($this->position < $this->length) {
            // Check for expression start {{
            if ($this->peek(2) === '{{') {
                $tokens[] = $this->makeToken(TokenType::EXPR_START, '{{', 2);
                $this->advance(2);
                
                // Tokenize expression content
                $tokens = array_merge($tokens, $this->tokenizeExpression());
                continue;
            }
            
            // Otherwise it's text
            $tokens[] = $this->tokenizeText();
        }
        
        $tokens[] = new Token(TokenType::EOF, '', $this->position, 0);
        
        return $tokens;
    }
    
    /**
     * Tokenize expression content (between {{ and }})
     *
     * @return Token[]
     * @throws LexerException
     */
    private function tokenizeExpression(): array
    {
        $tokens = [];
        
        while ($this->position < $this->length) {
            $this->skipWhitespace();
            
            if ($this->position >= $this->length) {
                throw new LexerException('Unexpected end of input in expression', $this->position);
            }
            
            // Check for expression end }}
            if ($this->peek(2) === '}}') {
                $tokens[] = $this->makeToken(TokenType::EXPR_END, '}}', 2);
                $this->advance(2);
                break;
            }
            
            $char = $this->current();
            
            // Single-character tokens
            if ($char === '(') {
                $tokens[] = $this->makeToken(TokenType::LPAREN, '(', 1);
                $this->advance(1);
            } elseif ($char === ')') {
                $tokens[] = $this->makeToken(TokenType::RPAREN, ')', 1);
                $this->advance(1);
            } elseif ($char === '.') {
                $tokens[] = $this->makeToken(TokenType::DOT, '.', 1);
                $this->advance(1);
            } elseif ($char === '[') {
                $tokens[] = $this->makeToken(TokenType::LBRACKET, '[', 1);
                $this->advance(1);
            } elseif ($char === ']') {
                $tokens[] = $this->makeToken(TokenType::RBRACKET, ']', 1);
                $this->advance(1);
            } elseif ($char === '*') {
                $tokens[] = $this->makeToken(TokenType::WILDCARD, '*', 1);
                $this->advance(1);
            } elseif ($char === '|') {
                $tokens[] = $this->makeToken(TokenType::PIPE, '|', 1);
                $this->advance(1);
            } elseif ($char === ',') {
                $tokens[] = $this->makeToken(TokenType::COMMA, ',', 1);
                $this->advance(1);
            } elseif ($char === "'") {
                $tokens[] = $this->tokenizeString();
            } elseif (ctype_digit($char)) {
                $tokens[] = $this->tokenizeInteger();
            } elseif (ctype_alpha($char) || $char === '_') {
                $tokens[] = $this->tokenizeIdentifier();
            } else {
                throw new LexerException(
                    "Unexpected character: {$char}",
                    $this->position
                );
            }
        }
        
        return $tokens;
    }
    
    private function tokenizeText(): Token
    {
        $start = $this->position;
        $text = '';
        
        while ($this->position < $this->length && $this->peek(2) !== '{{') {
            $text .= $this->current();
            $this->advance(1);
        }
        
        return new Token(TokenType::TEXT, $text, $start, strlen($text));
    }
    
    private function tokenizeString(): Token
    {
        $start = $this->position;
        $this->advance(1); // Skip opening '
        
        $value = '';
        
        while ($this->position < $this->length && $this->current() !== "'") {
            if ($this->current() === '\\' && $this->peek(1, 1) === "'") {
                $value .= "'";
                $this->advance(2);
            } elseif ($this->current() === '\\' && $this->peek(1, 1) === '\\') {
                $value .= '\\';
                $this->advance(2);
            } else {
                $value .= $this->current();
                $this->advance(1);
            }
        }
        
        if ($this->position >= $this->length) {
            throw new LexerException('Unterminated string', $start);
        }
        
        $this->advance(1); // Skip closing '
        
        return new Token(TokenType::STRING, $value, $start, $this->position - $start);
    }
    
    private function tokenizeInteger(): Token
    {
        $start = $this->position;
        $value = '';
        
        while ($this->position < $this->length && ctype_digit($this->current())) {
            $value .= $this->current();
            $this->advance(1);
        }
        
        return new Token(TokenType::INTEGER, $value, $start, strlen($value));
    }
    
    private function tokenizeIdentifier(): Token
    {
        $start = $this->position;
        $value = '';
        
        while ($this->position < $this->length && 
               (ctype_alnum($this->current()) || $this->current() === '_')) {
            $value .= $this->current();
            $this->advance(1);
        }
        
        // Check for keywords
        $type = match ($value) {
            'ref' => TokenType::REF,
            'field' => TokenType::FIELD,
            default => TokenType::IDENT,
        };
        
        return new Token($type, $value, $start, strlen($value));
    }
    
    private function skipWhitespace(): void
    {
        while ($this->position < $this->length && 
               ctype_space($this->current())) {
            $this->advance(1);
        }
    }
    
    private function current(): string
    {
        return $this->source[$this->position] ?? '';
    }
    
    private function peek(int $length, int $offset = 0): string
    {
        $pos = $this->position + $offset;
        if ($pos + $length > $this->length) {
            return '';
        }
        return substr($this->source, $pos, $length);
    }
    
    private function advance(int $count): void
    {
        $this->position += $count;
    }
    
    private function makeToken(TokenType $type, string $value, int $length): Token
    {
        $token = new Token($type, $value, $this->position, $length);
        return $token;
    }
}

