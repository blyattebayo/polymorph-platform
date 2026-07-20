<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Services;

use LogicException;
use Polymorph\Platform\Domain\Media\Core\Contracts\MediaIncludedProvider;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\Records\Core\Models\Record;

final class RecordIncludedDataAssembler
{
    public function __construct(
        private readonly RecordDisplayValueService $displayValueService,
        private readonly MediaIncludedProvider $mediaIncludedProvider,
    ) {}

    /**
     * @param  Record[]  $records
     * @param  string[]  $mediaIds
     * @return array{records: \stdClass, media: \stdClass}
     */
    public function buildIncluded(array $records, array $mediaIds): array
    {
        return [
            'records' => $this->buildRecordData($records),
            'media' => $this->mediaIncludedProvider->buildIncludedByIds($mediaIds),
        ];
    }

    /**
     * @param  Record[]  $records
     */
    private function buildRecordData(array $records): \stdClass
    {
        if ($records === []) {
            return new \stdClass;
        }

        $displayByRecordId = $this->displayValueService->computeForRecords($records);

        $result = new \stdClass;
        foreach ($records as $record) {
            $definition = $this->requireRecordDefinition($record);

            $result->{(string) $record->id} = [
                'record_display_value' => $displayByRecordId[(int) $record->id] ?? "Record #{$record->id}",
                'record_definition' => [
                    'id' => $definition->id,
                    'name' => $definition->name,
                ],
            ];
        }

        return $result;
    }

    private function requireRecordDefinition(Record $record): RecordDefinition
    {
        if (! $record->relationLoaded('recordDefinition')) {
            throw new LogicException(sprintf(
                'Record %d must have recordDefinition relation preloaded before included-data assembly.',
                (int) $record->id,
            ));
        }

        $definition = $record->getRelation('recordDefinition');
        if (! $definition instanceof RecordDefinition) {
            throw new LogicException(sprintf(
                'Record %d is missing an associated recordDefinition during included-data assembly.',
                (int) $record->id,
            ));
        }

        return $definition;
    }
}
