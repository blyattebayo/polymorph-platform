<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\Domain\DataPlatform\Delete\RecordDeleteCommand;
use Polymorph\Platform\Domain\DataPlatform\Delete\RecordDeleteCommandHandler;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformPreconditionRequired;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformResourceNotFound;
use Polymorph\Platform\Domain\DataPlatform\Query\QuerySpec;
use Polymorph\Platform\Domain\DataPlatform\Query\RecordQueryService;
use Polymorph\Platform\Domain\DataPlatform\Read\RecordLocator;
use Polymorph\Platform\Domain\DataPlatform\Read\RecordReadService;
use Polymorph\Platform\Domain\DataPlatform\Write\RecordCommandBus;
use Polymorph\Platform\Domain\DataPlatform\Write\RecordWriteCommand;

/** Thin transport adapter; all data semantics remain in the application layer. */
final readonly class DataRecordController
{
    public function __construct(
        private AuthenticationContext $auth,
        private RecordCommandBus $writes,
        private RecordDeleteCommandHandler $deletes,
        private RecordReadService $reads,
        private RecordQueryService $queries,
        private RecordLocator $records,
    ) {}

    public function store(Request $request, int $definitionId): JsonResponse
    {
        $this->records->assertDefinitionExists($definitionId);
        $payload = $request->validate([
            'data' => ['required', 'array'],
            'schema_version_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
        $result = $this->writes->dispatch(new RecordWriteCommand(
            recordDefinitionId: $definitionId,
            document: $payload['data'],
            actorId: $this->actorId(),
            idempotencyKey: $this->idempotencyKey($request, $payload),
            schemaVersionId: $payload['schema_version_id'] ?? null,
        ));

        $hydrated = $this->reads->hydrate([$result->recordId], $this->actorId(), ['relationships'], 1);
        $record = $hydrated['by_record_id'][(string) $result->recordId] ?? null;

        return response()->json(['data' => $record, 'meta' => [
            'operation_id' => $result->operationId,
            'no_op' => $result->noOp,
        ], 'included' => $hydrated['included']], 201)
            ->header('ETag', '"'.$result->revision.'"');
    }

    public function update(Request $request, int $definitionId, int $recordId): JsonResponse
    {
        $this->records->assertDefinitionExists($definitionId);
        $payload = $request->validate([
            'data' => ['required', 'array'],
            'expected_revision' => ['sometimes', 'integer', 'min:1'],
            'replace' => ['sometimes', 'boolean'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
        $expectedRevision = $this->expectedRevision($request, $payload);
        $result = $this->writes->dispatch(new RecordWriteCommand(
            recordDefinitionId: $definitionId,
            document: $payload['data'],
            actorId: $this->actorId(),
            recordId: $recordId,
            expectedRevision: $expectedRevision,
            idempotencyKey: $this->idempotencyKey($request, $payload),
            replace: (bool) ($payload['replace'] ?? false),
        ));

        $hydrated = $this->reads->hydrate([$result->recordId], $this->actorId(), ['relationships'], 1);
        $record = $hydrated['by_record_id'][(string) $result->recordId] ?? null;

        return response()->json(['data' => $record, 'meta' => [
            'operation_id' => $result->operationId,
            'no_op' => $result->noOp,
        ], 'included' => $hydrated['included']])
            ->header('ETag', '"'.$result->revision.'"');
    }

    public function show(Request $request, int $recordId): JsonResponse
    {
        $this->records->assertRecordExists($recordId);
        $include = $this->include($request->query('include', []));
        $depth = max(0, (int) $request->query('depth', 1));
        $hydrated = $this->reads->hydrate([$recordId], $this->actorId(), $include, $depth);
        $record = $hydrated['by_record_id'][(string) $recordId] ?? null;
        if ($record === null) {
            throw DataPlatformResourceNotFound::for('record', $recordId);
        }

        return response()->json(['data' => $record, 'included' => $hydrated['included']])
            ->header('ETag', '"'.$record['revision'].'"');
    }

    public function query(Request $request, int $definitionId): JsonResponse
    {
        $this->records->assertDefinitionExists($definitionId);
        $payload = $request->validate([
            'filter' => ['sometimes', 'array'],
            'sort' => ['sometimes', 'array'],
            'sort.*.field' => ['required_with:sort', 'string'],
            'sort.*.direction' => ['sometimes', 'in:asc,desc'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,500'],
            'include' => ['sometimes', 'array'],
            'include.*' => ['string', 'in:'.implode(',', RecordReadService::INCLUDE_VALUES)],
            'aggregate' => ['sometimes', 'array'],
            'group_by' => ['sometimes', 'array', 'max:5'],
            'group_by.*' => ['string'],
            'allow_scan' => ['sometimes', 'boolean'],
        ]);
        $payload['record_definition_id'] = $definitionId;

        return response()->json($this->queries->execute(QuerySpec::fromArray($payload), $this->actorId()));
    }

    public function hydrate(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'record_ids' => ['required', 'array', 'between:1,500'],
            'record_ids.*' => ['integer', 'min:1'],
            'include' => ['sometimes', 'array'],
            'include.*' => ['string', 'in:'.implode(',', RecordReadService::INCLUDE_VALUES)],
            'depth' => ['sometimes', 'integer', 'min:0'],
        ]);

        $this->records->assertRecordsExist(array_values(array_map('intval', $payload['record_ids'])));

        return response()->json($this->reads->hydrate(
            array_values(array_map('intval', $payload['record_ids'])),
            $this->actorId(),
            $payload['include'] ?? ['relationships'],
            (int) ($payload['depth'] ?? 1),
        ));
    }

    public function destroy(Request $request, int $recordId): JsonResponse
    {
        $this->records->assertRecordExists($recordId);
        $payload = $request->validate([
            'expected_revision' => ['sometimes', 'integer', 'min:1'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
        $result = $this->deletes->execute(new RecordDeleteCommand(
            recordId: $recordId,
            actorId: $this->actorId(),
            expectedRevision: $this->expectedRevision($request, $payload),
            idempotencyKey: $this->idempotencyKey($request, $payload),
        ));

        return response()->json(['data' => $result->toArray()]);
    }

    private function actorId(): int
    {
        return (int) $this->auth->requireUser()->id;
    }

    /** @param array<string,mixed> $payload */
    private function expectedRevision(Request $request, array $payload): int
    {
        if (isset($payload['expected_revision'])) {
            return (int) $payload['expected_revision'];
        }
        $etag = trim((string) $request->header('If-Match', ''), " \t\n\r\0\x0B\"");
        if (str_starts_with($etag, 'W/')) {
            $etag = trim(substr($etag, 2), '"');
        }
        if ($etag === '' || ! ctype_digit($etag) || (int) $etag < 1) {
            throw DataPlatformPreconditionRequired::required();
        }

        return (int) $etag;
    }

    /** @param array<string,mixed> $payload */
    private function idempotencyKey(Request $request, array $payload): ?string
    {
        $key = trim((string) ($payload['idempotency_key'] ?? $request->header('Idempotency-Key', '')));

        return $key === '' ? null : $key;
    }

    /** @return list<string> */
    private function include(mixed $value): array
    {
        $items = is_string($value) ? explode(',', $value) : (array) $value;

        return array_values(array_intersect(array_filter($items, 'is_string'), RecordReadService::INCLUDE_VALUES));
    }
}
