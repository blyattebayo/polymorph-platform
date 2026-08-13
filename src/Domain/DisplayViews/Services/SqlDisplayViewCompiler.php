<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DisplayViews\Services;

use LogicException;
use Polymorph\Platform\Domain\DisplayViews\Support\SqlJoinContext;
use Polymorph\Platform\Domain\SchemaModel\ReadModel\SchemaFieldSnapshot;
use Polymorph\Platform\Domain\SchemaModel\ReadModel\SchemaSnapshot;
use Polymorph\Platform\TemplateEngine\Core\AST\ExpressionNode;
use Polymorph\Platform\TemplateEngine\Core\AST\FieldNode;
use Polymorph\Platform\TemplateEngine\Core\AST\FilterNode;
use Polymorph\Platform\TemplateEngine\Core\AST\PathNode;
use Polymorph\Platform\TemplateEngine\Core\AST\RefNode;
use Polymorph\Platform\TemplateEngine\Core\AST\TemplateNode;
use Polymorph\Platform\TemplateEngine\Core\AST\TextNode;
use Polymorph\Platform\TemplateEngine\Core\AST\WildcardNode;
use Polymorph\Platform\TemplateEngine\Core\Errors\ValidationException;
use Polymorph\Platform\TemplateEngine\Core\Filters\FilterRegistry;

final class SqlDisplayViewCompiler
{
    private readonly FilterRegistry $filterRegistry;

    public function __construct(FilterRegistry $filterRegistry)
    {
        $this->filterRegistry = $filterRegistry;
    }

    /**
     * @return array{expression:string,template_hash:string}
     */
    public function compile(string $template, TemplateNode $ast, SchemaSnapshot $schema): array
    {
        $this->validate($ast, $schema);

        $parts = [];
        $hasRef = $this->templateHasRef($ast);
        $joinContext = $hasRef ? new SqlJoinContext : null;
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

    private function validate(TemplateNode $ast, SchemaSnapshot $schema): void
    {
        foreach ($ast->children as $child) {
            if (! $child instanceof ExpressionNode) {
                continue;
            }

            foreach ($child->filters as $filter) {
                $descriptor = $this->filterRegistry->get($filter->name);
                if ($descriptor !== null && ! $descriptor->supportsSql) {
                    throw new ValidationException("Filter '{$filter->name}' is not supported by SQL engine");
                }
            }

            $this->assertPathIsCompilable($child->path, $schema);
        }
    }

    private function assertPathIsCompilable(PathNode $path, SchemaSnapshot $schema): void
    {
        $expectedRecordDefinitionId = $schema->rootRecordDefinitionId;
        $segments = array_merge([$path->head], $path->segments);
        $refCount = count(array_filter($segments, static fn (mixed $segment): bool => $segment instanceof RefNode));

        if ($refCount > 1) {
            throw new ValidationException('SQL engine supports at most one ref() hop per expression path');
        }

        foreach ($segments as $index => $segment) {
            if ($segment instanceof WildcardNode) {
                throw new ValidationException('SQL engine does not support wildcard [*] yet');
            }

            if ($segment instanceof RefNode) {
                $field = $this->requireFieldMeta($schema, $segment->fieldId);
                if (! $field->isRef()) {
                    throw new ValidationException("ref({$segment->fieldId}) points to non-ref field");
                }
                if ($field->recordDefinitionId !== null && $field->recordDefinitionId !== $expectedRecordDefinitionId) {
                    throw new ValidationException(
                        "ref({$segment->fieldId}) is not reachable from record_definition {$expectedRecordDefinitionId}"
                    );
                }
                if ($field->allowedRecordDefinitionId !== null) {
                    $expectedRecordDefinitionId = $field->allowedRecordDefinitionId;
                }

                continue;
            }

            if (! $segment instanceof FieldNode) {
                throw new ValidationException('Unsupported path segment for SQL engine');
            }

            if ($index !== count($segments) - 1) {
                throw new ValidationException('Only ref() segments are allowed before terminal field()');
            }

            $field = $this->requireFieldMeta($schema, $segment->fieldId);
            if ($field->recordDefinitionId !== null && $field->recordDefinitionId !== $expectedRecordDefinitionId) {
                throw new ValidationException(
                    "field({$segment->fieldId}) is not reachable for record_definition {$expectedRecordDefinitionId} after ref traversal"
                );
            }

            return;
        }

        throw new ValidationException('Path must end with field() accessor');
    }

    private function requireFieldMeta(SchemaSnapshot $schema, int $fieldId): SchemaFieldSnapshot
    {
        $field = $schema->fieldsById[$fieldId] ?? null;
        if (! $field instanceof SchemaFieldSnapshot) {
            throw new ValidationException("Unknown field({$fieldId}) in template for record_definition {$schema->rootRecordDefinitionId}");
        }

        return $field;
    }

    /**
     * @param  array<string, string>  $refAliasByKey
     */
    private function compileExpression(
        ExpressionNode $expression,
        SchemaSnapshot $schema,
        string $baseAlias,
        ?SqlJoinContext $joinContext,
        array &$refAliasByKey,
    ): string {
        $expr = $this->compilePath($expression->path, $schema, $baseAlias, $joinContext, $refAliasByKey);

        foreach ($expression->filters as $filter) {
            $expr = $this->applyFilter($expr, $filter);
        }

        return "COALESCE(($expr)::text, '')";
    }

    /**
     * @param  array<string, string>  $refAliasByKey
     */
    private function compilePath(
        PathNode $path,
        SchemaSnapshot $schema,
        string $baseAlias,
        ?SqlJoinContext $joinContext,
        array &$refAliasByKey,
    ): string {
        $currentAlias = $baseAlias;
        $segments = array_merge([$path->head], $path->segments);

        foreach ($segments as $index => $segment) {
            if ($segment instanceof RefNode) {
                if (! $joinContext instanceof SqlJoinContext) {
                    throw new LogicException('Join context is required when compiling ref() traversal');
                }

                $field = $this->resolveFieldMeta($schema, $segment->fieldId);

                $joinKey = $currentAlias.'|'.$field->id;
                $nextAlias = $refAliasByKey[$joinKey] ?? null;
                if (! is_string($nextAlias)) {
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

            if (! $segment instanceof FieldNode) {
                throw new LogicException('Validated path contains unsupported segment type');
            }

            $isLast = $index === count($segments) - 1;
            if (! $isLast) {
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
            if (! $child instanceof ExpressionNode) {
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
        if (! $field instanceof SchemaFieldSnapshot) {
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
        return "'".str_replace("'", "''", $value)."'";
    }
}
