<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Services;

use Polymorph\Platform\Domain\Records\Core\Models\Record;
use Polymorph\Platform\Domain\Records\Infrastructure\Extractors\RecordMediaExtractor;
use Polymorph\Platform\Domain\Records\Infrastructure\Extractors\RecordRefExtractor;
use Polymorph\Platform\Domain\Records\Support\RecordDataNormalizer;
use Polymorph\Platform\Domain\Records\Support\RecordSchemaResolver;
use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\SchemaFieldPathReadModel;

final class RecordRelationshipResolver
{
    public function __construct(
        private readonly RecordRefExtractor $refExtractor,
        private readonly RecordMediaExtractor $mediaExtractor,
        private readonly SchemaFieldPathReadModel $schemaFieldPathReadModel,
    ) {
    }

    /**
     * @param iterable<Record> $records
     * @return array{relationships_by_record: array<int, array{records:int[],media:string[]}>, record_ids: int[], media_ids: string[]}
     */
    public function resolveBatch(iterable $records): array
    {
        $records = $this->materializeRecords($records);

        $pathsBySchemaId = $this->prefetchPathsBySchemaId($records);

        $relationshipsByRecord = [];
        $allRecordIds = [];
        $allMediaIds = [];

        foreach ($records as $record) {
            $schemaId = RecordSchemaResolver::fromRecord($record);
            $paths = $schemaId !== null
                ? ($pathsBySchemaId[$schemaId] ?? ['ref' => [], 'media' => []])
                : ['ref' => [], 'media' => []];

            $recordIds = $this->refExtractor->extractRefRecordIds(
                RecordDataNormalizer::fromMixed($record->data_json),
                $paths['ref']
            );
            $mediaIds = $this->mediaExtractor->extractMediaIds($record, $paths['media']);

            [$recordIds, $mediaIds] = $this->normalizeRelationshipIds($recordIds, $mediaIds);

            $relationshipsByRecord[(int) $record->id] = [
                'records' => $recordIds,
                'media' => $mediaIds,
            ];

            $allRecordIds = array_merge($allRecordIds, $recordIds);
            $allRecordIds[] = (int) $record->id;
            $allMediaIds = array_merge($allMediaIds, $mediaIds);
        }

        [$allRecordIds, $allMediaIds] = $this->normalizeRelationshipIds($allRecordIds, $allMediaIds);

        return [
            'relationships_by_record' => $relationshipsByRecord,
            'record_ids' => $allRecordIds,
            'media_ids' => $allMediaIds,
        ];
    }

    /**
     * @param array<int|string> $recordIds
     * @param array<int|string> $mediaIds
     * @return array{0: int[], 1: string[]}
     */
    private function normalizeRelationshipIds(array $recordIds, array $mediaIds): array
    {
        $recordIds = array_values(array_unique(array_filter($recordIds, fn ($id) => is_int($id) || ctype_digit((string) $id))));
        $recordIds = array_map(static fn ($id) => (int) $id, $recordIds);

        $mediaIds = array_values(array_unique(array_filter($mediaIds, fn ($id) => is_string($id) && $id !== '')));

        return [$recordIds, $mediaIds];
    }

    /**
     * @param iterable<Record> $records
     * @return list<Record>
     */
    private function materializeRecords(iterable $records): array
    {
        if (is_array($records)) {
            return array_values($records);
        }

        return iterator_to_array($records, false);
    }

    /**
     * @param iterable<Record> $records
     * @return array<int, array{ref: string[], media: string[]}>
     */
    private function prefetchPathsBySchemaId(iterable $records): array
    {
        $schemaIds = [];

        foreach ($records as $record) {
            $schemaId = RecordSchemaResolver::fromRecord($record);
            if ($schemaId !== null) {
                $schemaIds[] = $schemaId;
            }
        }

        return $this->schemaFieldPathReadModel->schemaPathsBySchemaIds($schemaIds);
    }
}