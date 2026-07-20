<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Services;

use Polymorph\Platform\Domain\SchemaModel\ReadModel\SchemaFieldSnapshot;
use Polymorph\Platform\Domain\SchemaModel\ReadModel\SchemaSnapshot;
use Polymorph\Platform\TemplateEngine\Core\AST\ExpressionNode;
use Polymorph\Platform\TemplateEngine\Core\AST\FieldNode;
use Polymorph\Platform\TemplateEngine\Core\AST\PathNode;
use Polymorph\Platform\TemplateEngine\Core\AST\RefNode;
use Polymorph\Platform\TemplateEngine\Core\AST\TemplateNode;
use Polymorph\Platform\TemplateEngine\Core\AST\WildcardNode;
use Polymorph\Platform\TemplateEngine\Core\Errors\ValidationException;
use Polymorph\Platform\TemplateEngine\Core\Filters\FilterRegistry;

final class SqlViewValidator
{
    private readonly FilterRegistry $filterRegistry;

    public function __construct(FilterRegistry $filterRegistry)
    {
        $this->filterRegistry = $filterRegistry;
    }

    public function validate(TemplateNode $ast, SchemaSnapshot $schema): void
    {
        $this->assertSqlSupportedFilters($ast);

        foreach ($ast->children as $child) {
            if (! $child instanceof ExpressionNode) {
                continue;
            }

            $this->assertSqlPathIsCompilable($child->path, $schema);
        }
    }

    private function assertSqlSupportedFilters(TemplateNode $ast): void
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
        }
    }

    private function assertSqlPathIsCompilable(PathNode $path, SchemaSnapshot $schema): void
    {
        $expectedRecordDefinitionId = $schema->rootRecordDefinitionId;
        $segments = array_merge([$path->head], $path->segments);

        if ($this->countRefSegments($segments) > 1) {
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

            $isLast = $index === count($segments) - 1;
            if (! $isLast) {
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

    /**
     * @param  array<int, mixed>  $segments
     */
    private function countRefSegments(array $segments): int
    {
        $count = 0;

        foreach ($segments as $segment) {
            if ($segment instanceof RefNode) {
                $count++;
            }
        }

        return $count;
    }

    private function requireFieldMeta(SchemaSnapshot $schema, int $fieldId): SchemaFieldSnapshot
    {
        $field = $schema->fieldsById[$fieldId] ?? null;
        if (! $field instanceof SchemaFieldSnapshot) {
            throw new ValidationException("Unknown field({$fieldId}) in template for record_definition {$schema->rootRecordDefinitionId}");
        }

        return $field;
    }
}
