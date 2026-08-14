<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Access\DataAccessPolicy;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformInvariantViolation;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformStateConflict;
use Polymorph\Platform\Domain\DataPlatform\Fields\DocumentPathAccessor;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaFieldMapper;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaStorage;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;
use Polymorph\Platform\TemplateEngine\Core\AST\ExpressionNode;
use Polymorph\Platform\TemplateEngine\Core\AST\FieldNode;
use Polymorph\Platform\TemplateEngine\Core\AST\RefNode;
use Polymorph\Platform\TemplateEngine\Core\AST\TemplateNode;
use Polymorph\Platform\TemplateEngine\Core\AST\TextNode;
use Polymorph\Platform\TemplateEngine\Core\Filters\FilterRegistry;
use Polymorph\Platform\TemplateEngine\Core\Pipeline\TemplateParsePipeline;

/** Evaluates the shared display-template AST against versioned documents. */
final class DisplayTemplateRenderer
{
    /** @var array<string,object> */
    private array $fieldCache = [];

    /** @var array<int,array<string,mixed>> */
    private array $definitionMetadata = [];

    /** @var array<string,list<FieldDefinition>> */
    private array $schemaFields = [];

    /** @var array<int,object|null> */
    private array $targetRecords = [];

    /** @var array<string,bool> */
    private array $targetReadability = [];

    /** @var array<int,bool> */
    private array $referenceTemplates = [];

    /** @var array<string,TemplateNode> */
    private array $templateAsts = [];

    public function __construct(
        private readonly TemplateParsePipeline $templates,
        private readonly FilterRegistry $filters,
        private readonly DocumentPathAccessor $paths,
        private readonly SchemaFieldMapper $fields,
        private readonly DatabaseJson $json,
    ) {}

    /** Clears request-scoped memoization at a public read/write operation boundary. */
    public function beginOperation(): void
    {
        $this->fieldCache = [];
        $this->definitionMetadata = [];
        $this->schemaFields = [];
        $this->targetRecords = [];
        $this->targetReadability = [];
        $this->referenceTemplates = [];
        $this->templateAsts = [];
    }

    /** @param array<string,mixed> $document */
    public function render(int $definitionId, string $schemaVersionId, array $document): ?string
    {
        $metadata = $this->metadata($definitionId);
        $source = trim((string) ($metadata['display_template'] ?? ''));
        if ($source === '') {
            return null;
        }

        $ast = $this->ast($source);
        $output = '';
        foreach ($ast->children as $node) {
            if ($node instanceof TextNode) {
                $output .= $node->text;

                continue;
            }
            if (! $node instanceof ExpressionNode) {
                continue;
            }
            $this->assertReferenceDepth($node);
            $value = $this->evaluate($definitionId, $schemaVersionId, $document, $node);
            foreach ($node->filters as $filter) {
                $descriptor = $this->filters->get($filter->name);
                if ($descriptor === null || ! is_callable($descriptor->handler)) {
                    throw DataPlatformInvariantViolation::because(
                        'display_filter_not_executable',
                        "Display filter '{$filter->name}' is not executable.",
                        ['filter' => $filter->name],
                    );
                }
                $value = ($descriptor->handler)($value, ...$filter->args);
            }
            $output .= $this->stringify($value);
        }

        return trim($output);
    }

    /**
     * Preloads the bounded relationship graph used by display templates. The
     * number of SQL queries depends on configured depth, never on page size.
     *
     * @param  array<int,int>  $sourceDefinitions  source record ID => definition ID
     */
    public function primeTargets(
        array $sourceDefinitions,
        ?int $actorId = null,
        ?DataAccessPolicy $access = null,
    ): void {
        $frontier = [];
        foreach ($sourceDefinitions as $recordId => $definitionId) {
            $recordId = (int) $recordId;
            if ($recordId > 0 && $this->hasReferenceTemplate((int) $definitionId)) {
                $frontier[] = $recordId;
            }
        }
        $frontier = array_values(array_unique($frontier));
        $visited = array_fill_keys($frontier, true);
        $maxDepth = max(0, (int) config('data_platform.display.max_ref_depth'));
        for ($level = 0; $level < $maxDepth && $frontier !== []; $level++) {
            $targetIds = DB::table('dp_ref_edges')->whereIn('source_record_id', $frontier)
                ->pluck('target_record_id')->map('intval')->unique()->values()->all();
            $loadableIds = $targetIds;
            $prefix = ($actorId ?? 'none').':';
            if ($access !== null && $targetIds !== []) {
                $uncheckedIds = array_values(array_filter(
                    $targetIds,
                    fn (int $id): bool => ! array_key_exists($prefix.$id, $this->targetReadability),
                ));
                if ($uncheckedIds !== []) {
                    $readable = array_fill_keys($access->readableTargetRecordIds($actorId, $uncheckedIds), true);
                    foreach ($uncheckedIds as $targetId) {
                        $this->targetReadability[$prefix.$targetId] = isset($readable[$targetId]);
                    }
                }
                $loadableIds = array_values(array_filter(
                    $targetIds,
                    fn (int $id): bool => $this->targetReadability[$prefix.$id] ?? false,
                ));
            }
            $missingIds = array_values(array_filter(
                $loadableIds,
                fn (int $id): bool => ! array_key_exists($id, $this->targetRecords),
            ));
            if ($missingIds !== []) {
                $rows = DB::table('dp_records')->whereIn('id', $missingIds)->whereNull('deleted_at')->get()->keyBy('id');
                foreach ($missingIds as $targetId) {
                    $this->targetRecords[$targetId] = $rows->get($targetId);
                }
            }
            $frontier = [];
            foreach ($loadableIds as $targetId) {
                $target = $this->targetRecords[$targetId] ?? null;
                if (isset($visited[$targetId])) {
                    continue;
                }
                $visited[$targetId] = true;
                if ($target !== null) {
                    $frontier[] = $targetId;
                }
            }
        }
    }

    /** Field- and target-aware guard for exposing a shared display projection. */
    public function canExpose(
        int $definitionId,
        string $schemaVersionId,
        array $document,
        ?int $actorId,
        DataAccessPolicy $access,
    ): bool {
        $metadata = $this->metadata($definitionId);
        $source = trim((string) ($metadata['display_template'] ?? ''));
        if ($source === '') {
            $fields = $this->schemaFields[$schemaVersionId] ??= $this->fields->fromRows(
                SchemaStorage::orderedFields(
                    DB::table('dp_schema_fields')->where('schema_version_id', $schemaVersionId),
                )->get(),
            );
            foreach ($fields as $field) {
                if (($field->metadata['display'] ?? false) === true
                    && ! $access->canReadField($actorId, $definitionId, $field)) {
                    return false;
                }
            }

            return true;
        }

        $ast = $this->ast($source);
        foreach ($ast->children as $node) {
            if (! $node instanceof ExpressionNode) {
                continue;
            }
            $this->assertReferenceDepth($node);
            if (! $this->resolveExpression(
                $definitionId,
                $schemaVersionId,
                $document,
                $node,
                $actorId,
                $access,
            )['allowed']) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $document */
    private function evaluate(
        int $definitionId,
        string $schemaVersionId,
        array $document,
        ExpressionNode $expression,
    ): mixed {
        return $this->resolveExpression($definitionId, $schemaVersionId, $document, $expression)['value'];
    }

    /** @param array<string,mixed> $document @return array{allowed:bool,value:mixed} */
    private function resolveExpression(
        int $definitionId,
        string $schemaVersionId,
        array $document,
        ExpressionNode $expression,
        ?int $actorId = null,
        ?DataAccessPolicy $access = null,
    ): array {
        $activeDefinitionId = $definitionId;
        $activeVersionId = $schemaVersionId;
        $activeDocument = $document;
        $value = null;
        foreach ([$expression->path->head, ...$expression->path->segments] as $segment) {
            if (! $segment instanceof FieldNode && ! $segment instanceof RefNode) {
                continue;
            }
            $field = $this->field($activeDefinitionId, $activeVersionId, $segment->fieldId);
            if ($access !== null) {
                $definition = $this->fields->fromRow($field);
                if (! $access->canReadDefinition($actorId, $activeDefinitionId)
                    || ! $access->canReadField($actorId, $activeDefinitionId, $definition)) {
                    return ['allowed' => false, 'value' => null];
                }
            }
            $values = $this->paths->values($activeDocument, (string) $field->path);
            $value = $values[0]['value'] ?? null;
            if ($segment instanceof RefNode) {
                $targetId = is_array($value) ? ($value['id'] ?? null) : $value;
                if (! is_numeric($targetId)
                    || ($access !== null && ! $this->canReadTarget($access, $actorId, (int) $targetId))) {
                    return ['allowed' => false, 'value' => null];
                }
                $target = $this->target((int) $targetId);
                if ($target === null) {
                    return ['allowed' => false, 'value' => null];
                }
                $activeDefinitionId = (int) $target->record_definition_id;
                $activeVersionId = (string) $target->schema_version_id;
                $activeDocument = $this->json->decodeMap($target->data, 'dp_records.data');
                $value = null;
            }
        }

        return ['allowed' => true, 'value' => $value];
    }

    private function field(int $definitionId, string $versionId, string $identifier): object
    {
        $key = $versionId.':'.$identifier;
        if (isset($this->fieldCache[$key])) {
            return $this->fieldCache[$key];
        }
        $query = DB::table('dp_schema_fields as schema_field')
            ->join('dp_fields as stable_field', 'stable_field.id', '=', 'schema_field.field_id')
            ->where('schema_field.schema_version_id', $versionId)
            ->where('stable_field.record_definition_id', $definitionId);
        $query->where('stable_field.id', $identifier);
        $field = $query->first(['schema_field.*']);
        if ($field === null) {
            throw DataPlatformStateConflict::because(
                'display_template_unknown_field',
                "Display template references unknown field '{$identifier}'.",
                ['field_id' => $identifier, 'record_definition_id' => $definitionId],
            );
        }

        return $this->fieldCache[$key] = $field;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return $this->json->encode($value);
    }

    private function target(int $recordId): ?object
    {
        if (array_key_exists($recordId, $this->targetRecords)) {
            return $this->targetRecords[$recordId];
        }

        return $this->targetRecords[$recordId] = DB::table('dp_records')
            ->where('id', $recordId)->whereNull('deleted_at')->first();
    }

    private function canReadTarget(DataAccessPolicy $access, ?int $actorId, int $recordId): bool
    {
        $key = ($actorId ?? 'none').':'.$recordId;

        return $this->targetReadability[$key] ??= $access->canReadTargetRecord($actorId, $recordId);
    }

    private function hasReferenceTemplate(int $definitionId): bool
    {
        return $this->referenceTemplates[$definitionId] ??= (function () use ($definitionId): bool {
            $source = trim((string) ($this->metadata($definitionId)['display_template'] ?? ''));
            if ($source === '') {
                return false;
            }
            foreach ($this->ast($source)->children as $node) {
                if (! $node instanceof ExpressionNode) {
                    continue;
                }
                foreach ([$node->path->head, ...$node->path->segments] as $segment) {
                    if ($segment instanceof RefNode) {
                        return true;
                    }
                }
            }

            return false;
        })();
    }

    private function ast(string $source): TemplateNode
    {
        return $this->templateAsts[$source] ??= $this->templates->parseAndValidate($source);
    }

    private function assertReferenceDepth(ExpressionNode $expression): void
    {
        $depth = 0;
        foreach ([$expression->path->head, ...$expression->path->segments] as $segment) {
            if ($segment instanceof RefNode) {
                $depth++;
            }
        }
        $maxDepth = max(0, (int) config('data_platform.display.max_ref_depth'));
        if ($depth > $maxDepth) {
            throw DataPlatformStateConflict::because(
                'display_template_reference_depth_exceeded',
                "Display template reference depth exceeds {$maxDepth}.",
                ['depth' => $depth, 'maximum' => $maxDepth],
            );
        }
    }

    /** @return array<string,mixed> */
    private function metadata(int $definitionId): array
    {
        return $this->definitionMetadata[$definitionId] ??= (function () use ($definitionId): array {
            $metadata = DB::table('dp_record_definitions')->where('id', $definitionId)->value('metadata');

            return $this->json->decodeMap($metadata, SchemaStorage::DEFINITION_METADATA_CONTEXT);
        })();
    }
}
