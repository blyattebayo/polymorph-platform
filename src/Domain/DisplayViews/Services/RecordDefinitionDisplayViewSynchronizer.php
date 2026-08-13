<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DisplayViews\Services;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DisplayViews\Support\DisplayViewName;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\SchemaModel\ReadModel\SchemaSnapshotService;

final class RecordDefinitionDisplayViewSynchronizer
{
    public function __construct(
        private readonly SchemaSnapshotService $schemaSnapshots,
        private readonly DisplayTemplateCompiler $compiler,
    ) {}

    public function synchronize(RecordDefinition $recordDefinition): void
    {
        $recordDefinitionId = (int) $recordDefinition->id;
        $viewName = DisplayViewName::forRecordDefinition($recordDefinitionId);
        $this->schemaSnapshots->clearCacheForRecordDefinition($recordDefinitionId);
        $schema = $this->schemaSnapshots->snapshotForRootRecordDefinition($recordDefinitionId);

        $compiled = $this->compiler->compile(
            $recordDefinitionId,
            $recordDefinition->getDisplayTemplate(),
            $schema,
        );

        $sql = sprintf(
            'CREATE OR REPLACE VIEW %s AS SELECT src.id, %s AS display_value, %s::text AS template_hash FROM records src WHERE src.record_definition_id = %d AND src.deleted_at IS NULL',
            DisplayViewName::quote($viewName),
            $compiled['expression'],
            $this->quoteLiteral($compiled['template_hash']),
            $recordDefinitionId,
        );

        DB::statement($sql);
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

                    $this->synchronize($recordDefinition);
                    $count++;
                }
            });

        return $count;
    }

    public function synchronizeSchema(int $schemaId): int
    {
        if ($schemaId <= 0) {
            return 0;
        }

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

                    $this->synchronize($recordDefinition);
                    $count++;
                }
            });

        return $count;
    }

    public function drop(int $recordDefinitionId): void
    {
        if ($recordDefinitionId <= 0) {
            return;
        }

        DB::statement(sprintf(
            'DROP VIEW IF EXISTS %s',
            DisplayViewName::quote(DisplayViewName::forRecordDefinition($recordDefinitionId)),
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

                    $this->drop((int) $recordDefinition->id);
                    $count++;
                }
            });

        return $count;
    }

    private function quoteLiteral(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
