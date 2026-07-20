<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Services;

use Polymorph\Platform\TemplateEngine\Core\AST\ExpressionNode;
use Polymorph\Platform\TemplateEngine\Core\AST\FieldNode;
use Polymorph\Platform\TemplateEngine\Core\AST\FilterNode;
use Polymorph\Platform\TemplateEngine\Core\AST\PathNode;
use Polymorph\Platform\TemplateEngine\Core\AST\RefNode;
use Polymorph\Platform\TemplateEngine\Core\AST\TemplateNode;
use Polymorph\Platform\TemplateEngine\Core\AST\TextNode;
use Polymorph\Platform\TemplateEngine\Core\Filters\FilterRegistry;
use Polymorph\Platform\Domain\SchemaModel\ReadModel\SchemaFieldSnapshot;
use Polymorph\Platform\Domain\SchemaModel\ReadModel\SchemaSnapshot;
use LogicException;

final class SqlViewCompiler
{
    private readonly FilterRegistry $filterRegistry;

    public function __construct(FilterRegistry $filterRegistry)
    {
        $this->filterRegistry = $filterRegistry;
    }

    /**
     * @return array{expression:string,template_hash:string}
     */
    public function compileValidatedDisplayTemplate(string $template, TemplateNode $ast, SchemaSnapshot $schema): array
    {
        $parts = [];
        $hasRef = $this->templateHasRef($ast);
        $joinContext = $hasRef ? new SqlJoinContext() : null;
        $refAliasByKey = [];

        foreach ($ast->children as $child) {
            if ($child instanceof TextNode) {
                $parts[] = $this->sqlStringLiteral($child->text);
                continue;
            }

            if ($child instanceof ExpressionNode) {
                $parts[] = $this->compileExpression(
                    $child,
                    $schema,
                    $hasRef ? 'e' : 'src',
                    $joinContext,
                    $refAliasByKey,
                );
            }
        }

        if ($parts === []) {
            $parts[] = "''";
        }

        $joinedExpression = implode(' || ', $parts);

        return [
            'expression' => $joinContext instanceof SqlJoinContext
                ? $joinContext->renderWithJoins($joinedExpression)
                : $joinedExpression,
            'template_hash' => hash('sha256', $template),
        ];
    }

    /**
     * @param array<string, string> $refAliasByKey
     */
    private function compileExpression(
        ExpressionNode $expression,
        SchemaSnapshot $schema,
        string $baseAlias,
        ?SqlJoinContext $joinContext,
        array &$refAliasByKey,
    ): string
    {
        $expr = $this->compilePath($expression->path, $schema, $baseAlias, $joinContext, $refAliasByKey);

        foreach ($expression->filters as $filter) {
            $expr = $this->applyFilter($expr, $filter);
        }

        return "COALESCE(($expr)::text, '')";
    }

    /**
     * @param array<string, string> $refAliasByKey
     */
    private function compilePath(
        PathNode $path,
        SchemaSnapshot $schema,
        string $baseAlias,
        ?SqlJoinContext $joinContext,
        array &$refAliasByKey,
    ): string
    {
        $currentAlias = $baseAlias;
        $segments = array_merge([$path->head], $path->segments);

        foreach ($segments as $index => $segment) {
            if ($segment instanceof RefNode) {
                if (!$joinContext instanceof SqlJoinContext) {
                    throw new LogicException('Join context is required when compiling ref() traversal');
                }

                $field = $this->resolveFieldMeta($schema, $segment->fieldId);

                $joinKey = $currentAlias . '|' . $field->id;
                $nextAlias = $refAliasByKey[$joinKey] ?? null;
                if (!is_string($nextAlias)) {
                    $nextAlias = $joinContext->nextAlias();
                    $joinContext->addLeftJoin(
                        $nextAlias,
                        $this->refJoinCondition($currentAlias, $nextAlias, $field)
                    );
                    $refAliasByKey[$joinKey] = $nextAlias;
                }

                $currentAlias = $nextAlias;
                continue;
            }

            if (!$segment instanceof FieldNode) {
                throw new LogicException('Validated path contains unsupported segment type');
            }

            $isLast = $index === count($segments) - 1;
            if (!$isLast) {
                throw new LogicException('Validated path must terminate with field() accessor');
            }

            $field = $this->resolveFieldMeta($schema, $segment->fieldId);

            return $this->jsonTextExpr($currentAlias, $field);
        }

        throw new LogicException('Validated path must end with field() accessor');
    }

    private function templateHasRef(TemplateNode $template): bool
    {
        foreach ($template->children as $child) {
            if (!$child instanceof ExpressionNode) {
                continue;
            }

            if ($child->path->head instanceof RefNode) {
                return true;
            }

            foreach ($child->path->segments as $segment) {
                if ($segment instanceof RefNode) {
                    return true;
                }
            }
        }

        return false;
    }

    private function applyFilter(string $expr, FilterNode $filter): string
    {
        return $this->filterRegistry->compileSql($filter->name, $expr, $filter->args);
    }

    private function resolveFieldMeta(SchemaSnapshot $schema, int $fieldId): SchemaFieldSnapshot
    {
        $field = $schema->fieldsById[$fieldId] ?? null;
        if (!$field instanceof SchemaFieldSnapshot) {
            throw new LogicException("Validated template references missing field({$fieldId}) during SQL compilation");
        }

        return $field;
    }

    private function jsonTextExpr(string $entryAlias, SchemaFieldSnapshot $field): string
    {
        $path = trim((string) ($field->dataPath !== '' ? $field->dataPath : $field->fullPath));
        if ($path === '') {
            $path = $field->name;
        }

        $parts = array_values(array_filter(explode('.', $path), static fn (string $part): bool => $part !== '' && $part !== '*'));
        if ($parts === []) {
            $parts = [$field->name];
        }

        $quoted = implode(', ', array_map(fn (string $part): string => $this->sqlStringLiteral($part), $parts));

        return "jsonb_extract_path_text(({$entryAlias}.data_json)::jsonb, {$quoted})";
    }

    private function refJoinCondition(string $fromAlias, string $toAlias, SchemaFieldSnapshot $refField): string
    {
        $refExpr = $this->jsonTextExpr($fromAlias, $refField);

        return "{$toAlias}.id = CASE WHEN ({$refExpr}) ~ '^[0-9]+$' THEN ({$refExpr})::bigint ELSE NULL END AND {$toAlias}.deleted_at IS NULL";
    }

    private function sqlStringLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}

