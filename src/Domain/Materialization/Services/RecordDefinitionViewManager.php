<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Services;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\SchemaModel\ReadModel\SchemaSnapshotService;
use Polymorph\Platform\TemplateEngine\Core\Pipeline\TemplateParsePipeline;

final class RecordDefinitionViewManager
{
    /** @var array<int, string> */
    private array $builtDefinitionHashByRecordDefinitionId = [];

    public function __construct(
        private readonly SchemaSnapshotService $schemaService,
        private readonly TemplateParsePipeline $templateParsePipeline,
        private readonly SqlViewValidator $sqlViewValidator,
        private readonly SqlViewCompiler $sqlViewCompiler,
    ) {}

    public function ensureViewForRecordDefinition(RecordDefinition $recordDefinition): string
    {
        $recordDefinitionId = (int) $recordDefinition->id;
        $viewName = $this->viewName($recordDefinitionId);
        $schema = $this->schemaService->snapshotForRootRecordDefinition($recordDefinitionId);

        $templateSource = $recordDefinition->getDisplayTemplate();
        $templateForHash = $templateSource === null || trim($templateSource) === ''
            ? ''
            : $templateSource;
        $templateHash = hash('sha256', $templateForHash);

        $definitionHash = hash('sha256', $templateHash.'|'.$schema->fullSchemaHash);
        if (($this->builtDefinitionHashByRecordDefinitionId[$recordDefinitionId] ?? null) === $definitionHash) {
            return $viewName;
        }

        if ($templateSource === null || trim($templateSource) === '') {
            $compiled = [
                'expression' => "('Record #' || src.id::text)",
                'template_hash' => hash('sha256', ''),
            ];
        } else {
            $validatedAst = $this->templateParsePipeline->parseAndValidate($templateSource);
            $this->sqlViewValidator->validate($validatedAst, $schema);
            $compiled = $this->sqlViewCompiler->compileValidatedDisplayTemplate(
                $templateSource,
                $validatedAst,
                $schema,
            );
        }

        $sql = sprintf(
            'CREATE OR REPLACE VIEW %s AS SELECT src.id, %s AS display_value, %s::text AS template_hash FROM records src WHERE src.record_definition_id = %d AND src.deleted_at IS NULL',
            $this->quoteIdentifier($viewName),
            $compiled['expression'],
            $this->quoteLiteral($compiled['template_hash']),
            $recordDefinitionId,
        );

        DB::statement($sql);
        $this->builtDefinitionHashByRecordDefinitionId[$recordDefinitionId] = $definitionHash;

        return $viewName;
    }

    public function rebuildAll(): int
    {
        $count = 0;

        RecordDefinition::query()
            ->select(['id', 'display_template'])
            ->orderBy('id')
            ->chunk(200, function ($recordDefinitions) use (&$count): void {
                foreach ($recordDefinitions as $recordDefinition) {
                    if (! $recordDefinition instanceof RecordDefinition) {
                        continue;
                    }

                    $this->ensureViewForRecordDefinition($recordDefinition);
                    $count++;
                }
            });

        return $count;
    }

    public function rebuildForSchema(int $schemaId): int
    {
        if ($schemaId <= 0) {
            return 0;
        }

        $this->schemaService->clearCacheForSchema($schemaId);

        $count = 0;

        RecordDefinition::query()
            ->select(['id', 'display_template', 'schema_id'])
            ->where('schema_id', $schemaId)
            ->orderBy('id')
            ->chunk(200, function ($recordDefinitions) use (&$count): void {
                foreach ($recordDefinitions as $recordDefinition) {
                    if (! $recordDefinition instanceof RecordDefinition) {
                        continue;
                    }

                    unset($this->builtDefinitionHashByRecordDefinitionId[(int) $recordDefinition->id]);

                    $this->ensureViewForRecordDefinition($recordDefinition);
                    $count++;
                }
            });

        return $count;
    }

    public function viewName(int $recordDefinitionId): string
    {
        if ($recordDefinitionId <= 0) {
            throw new \InvalidArgumentException('Record definition id must be positive for view naming');
        }

        return 'display_rd_'.$recordDefinitionId.'_v';
    }

    public function dropViewForRecordDefinitionId(int $recordDefinitionId): void
    {
        if ($recordDefinitionId <= 0) {
            return;
        }

        unset($this->builtDefinitionHashByRecordDefinitionId[$recordDefinitionId]);

        DB::statement(sprintf(
            'DROP VIEW IF EXISTS %s',
            $this->quoteIdentifier($this->viewName($recordDefinitionId)),
        ));
    }

    /**
     * Drop all display views tracked by current record definitions.
     */
    public function dropAllViews(): int
    {
        $count = 0;

        RecordDefinition::query()
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(200, function ($recordDefinitions) use (&$count): void {
                foreach ($recordDefinitions as $recordDefinition) {
                    if (! $recordDefinition instanceof RecordDefinition) {
                        continue;
                    }

                    $this->dropViewForRecordDefinitionId((int) $recordDefinition->id);
                    $count++;
                }
            });

        return $count;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    private function quoteLiteral(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
