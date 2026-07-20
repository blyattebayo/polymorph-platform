<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Services;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Materialization\Contracts\RecordDisplayValueProvider;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

final class MaterializedRecordDisplayValueProvider implements RecordDisplayValueProvider
{
    public function __construct(
        private readonly RecordDefinitionViewManager $viewManager,
        private readonly AppLogger $logger,
    ) {}

    /**
     * @param  int[]  $recordIds
     * @return array<int, string>
     */
    public function getDisplayValues(RecordDefinition $recordDefinition, array $recordIds): array
    {
        if ($recordIds === []) {
            return [];
        }

        $definitionId = (int) $recordDefinition->id;

        try {
            $viewName = $this->viewManager->ensureViewForRecordDefinition($recordDefinition);
            $placeholders = implode(', ', array_fill(0, count($recordIds), '?'));
            $rows = DB::select(
                sprintf(
                    'SELECT id, display_value FROM %s WHERE id IN (%s)',
                    $this->quoteIdentifier($viewName),
                    $placeholders,
                ),
                array_values($recordIds),
            );

            $result = [];
            foreach ($rows as $row) {
                $result[(int) $row->id] = isset($row->display_value)
                    ? (string) $row->display_value
                    : "Record #{$row->id}";
            }

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('materialization.display_value.lookup_failed', [
                'record_definition_id' => $definitionId,
                'exception' => $e,
            ]);

            return [];
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
