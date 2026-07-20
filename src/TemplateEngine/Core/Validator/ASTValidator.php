<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\Validator;

use Polymorph\Platform\TemplateEngine\Core\AST\{
    ExpressionNode,
    FieldNode,
    PathNode,
    RefNode,
    TemplateNode,
    TextNode,
    WildcardNode
};
use Polymorph\Platform\TemplateEngine\Core\Errors\ValidationException;
use Polymorph\Platform\TemplateEngine\Core\Filters\FilterRegistry;

/**
 * AST validator for the template engine.
 * 
 * Validates Abstract Syntax Tree after parsing.
 * Ensures semantic correctness before compilation to IR.
 * 
 * VALIDATION RULES:
 *
 * Path Validation (8 rules):
 * 1. Path must start with ref() or field() (not other node types)
 * 2. Only field(), ref(), and [*] allowed in path segments
 * 3. Path must end with field() accessor (ref() terminal is forbidden)
 * 4. Wildcard [*] is forbidden in SQL VIEW runtime
 * 5. (reserved)
 * 6. field() IDs must be positive integers (> 0)
 * 7. ref() ID must be positive integers (> 0)
 * 
 * Filter Validation (via FilterRegistry):
 * 8. Filter name must exist in registry
 * 9. Filter argument count must be within [minArgs, maxArgs] range
 * 
 * EXAMPLES:
 * ✅ Valid:
 * - {{ field(10) }}                        - Simple field access
 * - {{ ref(100).field(20) }}               - Ref traversal ending in field
 * - {{ field(10) | upper }}                - Filter without args
 * - {{ field(10) | truncate(50) }}         - Filter with args
 * 
 * Invalid:
 * - {{ ref(100) }}                         - Path must end with field() (rule 3)
 * - {{ field(10).ref(20) }}                - Path must end with field() (rule 3)
 * - {{ field(0) }}                         - Zero ID (rule 6)
 * - {{ field(-5) }}                        - Negative ID (rule 6)
 * - {{ ref(0) }}                           - Zero ref ID (rule 7)
 * - {{ field(10)[*] }}                     - Wildcard is not supported in SQL VIEW runtime
 * - {{ ref(10)[*] }}                       - Wildcard is not supported in SQL VIEW runtime
 * - {{ field(10) | unknown }}              - Unknown filter (filter rule 8)
 * - {{ field(10) | truncate }}             - Too few args (rule 9)
 * - {{ field(10) | upper(5) }}             - Too many args (rule 9)
 * 
 * ERROR REPORTING:
 * Throws ValidationException with:
 * - Descriptive error message
 * - Optional span information (spanStart, spanEnd) for source positions
 * - FieldPath information (when available)
 * 
 * @see Tests\Unit\TemplateEngine\ASTValidatorTest for comprehensive test coverage
 */
class ASTValidator
{
    public function __construct(
        private readonly FilterRegistry $filterRegistry
    ) {
    }

    /**
     * Validate the entire template AST.
     * 
     * @throws ValidationException
     */
    public function validate(TemplateNode $template): void
    {
        foreach ($template->children as $child) {
            if ($child instanceof TextNode) {
                // Text nodes are always valid
                continue;
            }

            if ($child instanceof ExpressionNode) {
                $this->validateExpression($child);
            }
        }
    }

    /**
     * Validate expression node
     */
    private function validateExpression(ExpressionNode $expr): void
    {
        $this->validatePath($expr->path);

        foreach ($expr->filters as $filter) {
            $this->filterRegistry->validate($filter->name, count($filter->args));
        }
    }

    /**
     * Validate path node (ref/field chain)
     */
    private function validatePath(PathNode $path): void
    {
        // Rule 1: Path must start with ref() or field()
        $this->validatePathHead($path);

        // Rule 2: Only field(), ref() and [*] allowed after head
        $this->validatePathSegments($path);

        // Rule 3: path must end with field()
        $this->validatePathEndsWithField($path);

        // Rule 4: wildcard [*] is forbidden in SQL VIEW runtime
        $this->validateNoWildcard($path);

        // Rule 6: field() IDs must be positive integers
        $this->validateFieldIds($path);

        // Rule 7: ref() ID must be positive integer
        $this->validateRefId($path);
    }

    /**
     * Rule 3: path must end with field() accessor
     */
    private function validatePathEndsWithField(PathNode $path): void
    {
        $lastAccessor = $path->head;

        foreach ($path->segments as $segment) {
            if ($segment instanceof FieldNode || $segment instanceof RefNode) {
                $lastAccessor = $segment;
            }
        }

        if (!($lastAccessor instanceof FieldNode)) {
            throw new ValidationException(
                'Path must end with field() accessor',
                spanStart: $path->start,
                spanEnd: $path->end
            );
        }
    }

    /**
     * Rule 1: Path must start with ref() or field()
     */
    private function validatePathHead(PathNode $path): void
    {
        if (!($path->head instanceof RefNode) && !($path->head instanceof FieldNode)) {
            throw new ValidationException(
                "Path must start with ref() or field()",
                spanStart: $path->start,
                spanEnd: $path->end
            );
        }
    }

    /**
     * Rule 2: Only field(), ref(), and [*] allowed in segments
     */
    private function validatePathSegments(PathNode $path): void
    {
        foreach ($path->segments as $segment) {
            if (!($segment instanceof RefNode) 
                && !($segment instanceof FieldNode) 
                && !($segment instanceof WildcardNode)) {
                throw new ValidationException(
                    "Path segments must be ref(), field(), or [*]",
                    spanStart: $segment->getSpan()[0],
                    spanEnd: $segment->getSpan()[1]
                );
            }
        }
    }

    /**
     * Rule 4: wildcard [*] is not supported by SQL VIEW runtime.
     */
    private function validateNoWildcard(PathNode $path): void
    {
        foreach ($path->segments as $segment) {
            if ($segment instanceof WildcardNode) {
                throw new ValidationException(
                    'Wildcard [*] is not supported by SQL VIEW runtime',
                    spanStart: $segment->start,
                    spanEnd: $segment->end
                );
            }
        }
    }

    /**
     * Rule 6: field() IDs must be positive integers
     */
    private function validateFieldIds(PathNode $path): void
    {
        $allFields = [];

        if ($path->head instanceof FieldNode) {
            $allFields[] = $path->head;
        }

        foreach ($path->segments as $segment) {
            if ($segment instanceof FieldNode) {
                $allFields[] = $segment;
            }
        }

        foreach ($allFields as $field) {
            if ($field->fieldId <= 0) {
                throw new ValidationException(
                    "field() ID must be a positive integer, got {$field->fieldId}",
                    spanStart: $field->start,
                    spanEnd: $field->end
                );
            }
        }
    }

    /**
     * Rule 7: ref() ID must be positive integer
     */
    private function validateRefId(PathNode $path): void
    {
        $allRefs = [];

        if ($path->head instanceof RefNode) {
            $allRefs[] = $path->head;
        }

        // Also validate RefNode in segments
        foreach ($path->segments as $segment) {
            if ($segment instanceof RefNode) {
                $allRefs[] = $segment;
            }
        }

        foreach ($allRefs as $ref) {
            if ($ref->fieldId <= 0) {
                throw new ValidationException(
                    "ref() ID must be a positive integer, got {$ref->fieldId}",
                    spanStart: $ref->start,
                    spanEnd: $ref->end
                );
            }
        }
    }
}

