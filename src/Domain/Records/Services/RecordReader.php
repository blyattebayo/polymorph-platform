<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Services;

use Polymorph\Platform\Domain\Records\Core\Contracts\RecordRepository;
use Polymorph\Platform\Domain\Records\Core\Models\Record;
use Polymorph\Platform\Domain\Records\Support\RecordPayloadPathRegistry;
use Polymorph\Platform\Infrastructure\Pagination\V2\LaravelPaginatorAdapter;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageResult;

final class RecordReader
{
    public function __construct(
        private readonly RecordRepository $recordRepository,
        private readonly RecordReadProfileResolver $profileResolver,
        private readonly RecordPayloadProjector $payloadProjector,
        private readonly RecordRelationshipResolver $recordRelationshipResolver,
        private readonly RecordIncludedDataAssembler $recordIncludedDataAssembler,
        private readonly LaravelPaginatorAdapter $paginatorAdapter,
    ) {}

    public function listForDefinition(
        ?UserIdentity $actor,
        int $recordDefinitionId,
        PageRequest $pagination,
    ): PageResult {
        $result = $this->paginatorAdapter->toPageResult(
            $this->recordRepository->paginateForDefinition($recordDefinitionId, $pagination)
        );
        $profile = $this->profileResolver->forDefinition($actor, $recordDefinitionId);

        return $result->mapItems(
            fn (Record $record): array => $this->presentRecord($record, $profile)
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function show(?UserIdentity $actor, int $recordId): ?array
    {
        $record = $this->recordRepository->findForRead($recordId);
        if (! $record instanceof Record) {
            return null;
        }

        return $this->presentRecord($record, $this->profileResolver->forRecord($actor, $record));
    }

    /**
     * @param  int[]  $recordIds
     * @return array{by_record_id: object, included: array<string, object>}
     */
    public function hydrate(?UserIdentity $actor, array $recordIds): array
    {
        if ($recordIds === []) {
            return [
                'by_record_id' => new \stdClass,
                'included' => [
                    'records' => new \stdClass,
                    'media' => new \stdClass,
                ],
            ];
        }

        /** @var array<int, Record> $records */
        $records = $this->recordRepository->findHydratableByIds($recordIds);

        $resolved = $this->recordRelationshipResolver->resolveBatch($records);
        $includedRecords = $this->recordRepository->findByIdsWithDefinition($resolved['record_ids']);

        $byRecordId = [];
        foreach ($records as $record) {
            $recordId = (int) $record->id;
            $byRecordId[(string) $recordId] = [
                RecordPayloadPathRegistry::ROOT_KEY => $this->payloadForRecord($actor, $record),
                'relationships' => $resolved['relationships_by_record'][$recordId] ?? ['records' => [], 'media' => []],
            ];
        }

        return [
            'by_record_id' => (object) $byRecordId,
            'included' => $this->recordIncludedDataAssembler->buildIncluded($includedRecords, $resolved['media_ids']),
        ];
    }

    public function payloadForRecord(?UserIdentity $actor, Record $record): object
    {
        return $this->payloadProjector->project(
            $record->data_json,
            $this->profileResolver->forRecord($actor, $record),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function presentLoadedRecord(?UserIdentity $actor, Record $record): array
    {
        return $this->presentRecord(
            $record,
            $this->profileResolver->forRecord($actor, $record),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRecord(Record $record, RecordReadProfile $profile): array
    {
        $result = [
            'id' => (int) $record->id,
            RecordPayloadPathRegistry::ROOT_KEY => $this->payloadProjector->project($record->data_json, $profile),
        ];

        if ($record->relationLoaded('recordDefinition')) {
            $recordDefinition = $record->recordDefinition;
            $result['record_definition'] = $recordDefinition === null
                ? null
                : [
                    'id' => (int) $recordDefinition->id,
                    'name' => (string) $recordDefinition->name,
                ];
        }

        if ($record->relationLoaded('author')) {
            $author = $record->author;
            $result['author'] = $author === null
                ? null
                : [
                    'id' => (int) $author->id,
                    'name' => (string) $author->name,
                ];
        }

        return $result;
    }
}
