<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Services;

use LogicException;
use Polymorph\Platform\Domain\DisplayViews\Services\SqlViewRecordDisplayValueReader;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\Records\Core\Models\Record;

final class RecordDisplayValueService
{
    public function __construct(
        private readonly SqlViewRecordDisplayValueReader $reader,
    ) {}

    /**
     * Compute display value for a single record.
     * Expects recordDefinition relation already loaded.
     */
    public function computeForRecord(Record $record): string
    {
        $results = $this->computeForRecords([$record]);

        return $results[(int) $record->id] ?? "Record #{$record->id}";
    }

    /**
     * Batch-compute display values.
     * Expects recordDefinition relation already loaded on all records.
     *
     * @param  Record[]  $records
     * @return array<int, string>
     */
    public function computeForRecords(array $records): array
    {
        $displayByRecordId = [];
        $groups = $this->groupByDefinition($records, $displayByRecordId);

        foreach ($groups as $group) {
            $resolved = $this->reader->read($group['definition'], $group['ids']);
            foreach ($resolved as $id => $displayValue) {
                $displayByRecordId[$id] = $displayValue;
            }
        }

        return $displayByRecordId;
    }

    /**
     * Split records into per-definition buckets, initialising defaults in $displayByRecordId.
     *
     * @param  Record[]  $records
     * @param  array<int, string>  $displayByRecordId  Populated as a side-effect.
     * @return array<int, array{definition: RecordDefinition, ids: int[]}>
     */
    private function groupByDefinition(array $records, array &$displayByRecordId): array
    {
        $groups = [];

        foreach ($records as $record) {
            $recordId = (int) $record->id;
            $displayByRecordId[$recordId] = "Record #{$recordId}";

            if (! $record->relationLoaded('recordDefinition')) {
                throw new LogicException(sprintf(
                    'Record %d must have recordDefinition relation preloaded before display value computation.',
                    $recordId,
                ));
            }

            $definition = $record->getRelation('recordDefinition');
            if (! $definition instanceof RecordDefinition) {
                throw new LogicException(sprintf(
                    'Record %d is missing an associated recordDefinition during display value computation.',
                    $recordId,
                ));
            }

            if ($definition->getDisplayTemplate() === null) {
                continue;
            }

            $definitionId = (int) $definition->id;
            $groups[$definitionId] ??= ['definition' => $definition, 'ids' => []];
            $groups[$definitionId]['ids'][] = $recordId;
        }

        return $groups;
    }
}
