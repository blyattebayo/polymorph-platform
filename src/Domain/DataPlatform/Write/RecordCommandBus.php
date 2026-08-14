<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Write;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Polymorph\Platform\Domain\DataPlatform\Access\DataAccessDenied;
use Polymorph\Platform\Domain\DataPlatform\Access\DataAccessPolicy;
use Polymorph\Platform\Domain\DataPlatform\Access\TrustedMaintenanceDataAccessPolicy;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformResourceNotFound;
use Polymorph\Platform\Domain\DataPlatform\Fields\DependencySet;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Outbox\RecordAuditEntry;
use Polymorph\Platform\Domain\DataPlatform\Outbox\RecordEventMessage;
use Polymorph\Platform\Domain\DataPlatform\Outbox\RecordEventType;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaState;
use Polymorph\Platform\Domain\DataPlatform\Validation\DataValidationException;

/** Sole transport-independent writer for records and synchronous projections. */
final class RecordCommandBus
{
    public function __construct(
        private readonly RecordCommandRuntime $runtime,
        private readonly DataAccessPolicy $access,
    ) {}

    /** @internal MaintenanceRecordCommandBus is the sole caller. */
    public function withMaintenanceAccess(TrustedMaintenanceDataAccessPolicy $access): self
    {
        return new self(
            $this->runtime,
            $access,
        );
    }

    public function dispatch(RecordWriteCommand $command): RecordWriteResult
    {
        return DB::transaction(function () use ($command): RecordWriteResult {
            $runtime = DB::table('dp_record_definitions')
                ->where('id', $command->recordDefinitionId)
                ->lockForUpdate()
                ->first(['id']);
            if ($runtime === null) {
                throw DataPlatformResourceNotFound::for('record-definition', $command->recordDefinitionId);
            }
            // Authorization precedes idempotency lookup so a replay cannot use
            // a payload captured before the actor's definition grant was
            // revoked.
            $this->authorizeDefinition($command);
            if ($command->schemaMigration && ! $this->access instanceof TrustedMaintenanceDataAccessPolicy) {
                throw DataAccessDenied::for('schema-migration', 'execute');
            }
            $requestHash = $this->requestHash($command);
            $replayed = $this->runtime->idempotency->claim(
                $command->actorId,
                $command->kind(),
                $command->idempotencyKey,
                $requestHash,
            );
            if ($replayed !== null) {
                return RecordWriteResult::fromArray($replayed);
            }

            $before = [];
            $currentRevision = 0;
            $record = null;
            $logicalVersionId = null;
            if ($command->recordId !== null) {
                $record = DB::table('dp_records')->where('id', $command->recordId)->lockForUpdate()->first();
                if ($record === null || $record->deleted_at !== null) {
                    throw DataPlatformResourceNotFound::for('record', $command->recordId);
                }
                if ((int) $record->record_definition_id !== $command->recordDefinitionId) {
                    throw DataPlatformBadRequest::because(
                        'record_definition_mismatch',
                        'Record does not belong to the requested definition.',
                        ['record_id' => $command->recordId, 'record_definition_id' => $command->recordDefinitionId],
                    );
                }
                $currentRevision = (int) $record->revision;
                if ($command->expectedRevision === null) {
                    throw DataPlatformBadRequest::because(
                        'missing_expected_revision',
                        'Updates require expectedRevision.',
                        ['record_id' => $command->recordId],
                    );
                }
                if ($command->expectedRevision !== $currentRevision) {
                    throw new OptimisticLockConflict($command->recordId, $command->expectedRevision, $currentRevision);
                }
                $storedDocument = $this->decodeDocument($record->data);
                if ($command->schemaMigration) {
                    $before = $storedDocument;
                } else {
                    $logical = $this->runtime->logicalDocuments->current(
                        $command->recordDefinitionId,
                        (string) $record->schema_version_id,
                        $storedDocument,
                    );
                    $before = $logical['document'];
                    $logicalVersionId = $logical['logical_schema_version_id'];
                    if ($command->schemaVersionId !== null && $command->schemaVersionId !== $logicalVersionId) {
                        throw DataPlatformBadRequest::because(
                            'record_schema_version_mismatch',
                            'An update must use the record logical schema version.',
                            [
                                'record_id' => $command->recordId,
                                'schema_version_id' => $command->schemaVersionId,
                                'logical_schema_version_id' => $logicalVersionId,
                            ],
                        );
                    }
                }
            }

            $schema = match (true) {
                $command->schemaMigration => $this->runtime->schemas->definitionVersion(
                    $command->recordDefinitionId,
                    $command->schemaVersionId,
                    [SchemaState::Backfilling, SchemaState::Published, SchemaState::Archived],
                ),
                $logicalVersionId !== null => $this->runtime->schemas->definitionVersion(
                    $command->recordDefinitionId,
                    $logicalVersionId,
                    [SchemaState::Published, SchemaState::Backfilling],
                ),
                default => $this->runtime->schemas->writableDefinition(
                    $command->recordDefinitionId,
                    $command->schemaVersionId,
                ),
            };
            $versionId = (string) $schema['version']['id'];
            $fields = $schema['fields'];

            $candidate = $command->recordId !== null && ! $command->replace
                ? $this->runtime->paths->mergePatch($before, $command->document)
                : $command->document;
            $candidate = $this->runtime->paths->ensureStableItemIds($candidate);
            $beforeValues = $this->encodedFieldValues($before, $fields);
            $candidateValues = $this->encodedFieldValues($candidate, $fields);
            $this->assertKnownAndOwnedFields(
                $candidate,
                $command->document,
                $fields,
                $beforeValues,
                $candidateValues,
            );
            $this->authorizeChangedFields($command, $fields, $beforeValues, $candidateValues);

            $normalized = $this->normalizeAndValidate($candidate, $fields);
            $this->resolveAndValidateDependencies($normalized, $fields);
            $normalizedValues = $this->encodedFieldValues($normalized, $fields);
            $this->runtime->projectionChanges->beginOperation();
            $changes = $this->buildChangeSet(
                $before,
                $normalized,
                $fields,
                $this->changedFieldIds($fields, $beforeValues, $normalizedValues),
                $command,
                $versionId,
            );
            if ($record !== null && (string) $record->schema_version_id !== $versionId) {
                $changes = new RecordChangeSet(
                    changedFieldIds: $changes->changedFieldIds,
                    projections: $changes->projections,
                    events: [[
                        'type' => RecordEventType::Migrated->value,
                        'changed_field_ids' => $changes->changedFieldIds,
                    ]],
                    noOp: false,
                );
            }
            $operationId = (string) Str::uuid();

            if ($changes->noOp && $command->recordId !== null) {
                $result = new RecordWriteResult(
                    $command->recordId,
                    $versionId,
                    $currentRevision,
                    $normalized,
                    true,
                    $operationId,
                );
                $this->runtime->idempotency->completeResult(
                    $command->actorId,
                    $command->kind(),
                    $command->idempotencyKey,
                    $requestHash,
                    $result,
                );

                return $result;
            }

            if ($command->recordId === null) {
                $revision = 1;
                $values = [
                    'record_definition_id' => $command->recordDefinitionId,
                    'schema_version_id' => $versionId,
                    'data' => $this->runtime->json->encode($normalized),
                    'revision' => $revision,
                    'author_id' => $command->actorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ];
                $recordId = (int) DB::table('dp_records')->insertGetId($values);
            } else {
                $revision = $currentRevision + 1;
                $affected = DB::table('dp_records')
                    ->where('id', $command->recordId)
                    ->where('revision', $command->expectedRevision)
                    ->whereNull('deleted_at')
                    ->update([
                        'schema_version_id' => $versionId,
                        'data' => $this->runtime->json->encode($normalized),
                        'revision' => $revision,
                        'author_id' => $command->actorId,
                        'updated_at' => now(),
                    ]);
                if ($affected !== 1) {
                    $actual = (int) DB::table('dp_records')->where('id', $command->recordId)->value('revision');
                    throw new OptimisticLockConflict($command->recordId, (int) $command->expectedRevision, $actual);
                }
                $recordId = $command->recordId;
            }

            $this->runtime->projections->replace($recordId, $command->recordDefinitionId, $changes->projections);
            $this->writeAuditAndOutbox($operationId, $command, $recordId, $revision, $changes);

            $result = new RecordWriteResult($recordId, $versionId, $revision, $normalized, false, $operationId);
            $this->runtime->idempotency->completeResult(
                $command->actorId,
                $command->kind(),
                $command->idempotencyKey,
                $requestHash,
                $result,
            );

            return $result;
        }, 3);
    }

    private function authorizeDefinition(RecordWriteCommand $command): void
    {
        if (! $this->access->canWriteDefinition($command->actorId, $command->recordDefinitionId)) {
            throw DataAccessDenied::for('record-definition.'.$command->recordDefinitionId, 'write');
        }
    }

    /**
     * @param  list<FieldDefinition>  $fields
     * @param  array<string,string>  $beforeValues
     * @param  array<string,string>  $candidateValues
     */
    private function authorizeChangedFields(
        RecordWriteCommand $command,
        array $fields,
        array $beforeValues,
        array $candidateValues,
    ): void {
        foreach ($fields as $field) {
            if ($beforeValues[$field->id] === $candidateValues[$field->id]) {
                continue;
            }
            if (! $this->access->canWriteField(
                $command->actorId,
                $command->recordDefinitionId,
                $field,
            )) {
                throw DataAccessDenied::for('field.'.$field->id, 'write');
            }
        }
    }

    /**
     * @param  list<FieldDefinition>  $fields
     * @param  array<string,string>  $beforeValues
     * @param  array<string,string>  $documentValues
     */
    private function assertKnownAndOwnedFields(
        array $document,
        array $incoming,
        array $fields,
        array $beforeValues,
        array $documentValues,
    ): void {
        $knownPaths = [];
        $containerPaths = [];
        foreach ($fields as $field) {
            $knownPaths[$field->path] = true;
            $segments = explode('.', $field->path);
            array_pop($segments);
            while ($segments !== []) {
                $containerPaths[implode('.', $segments)] = true;
                array_pop($segments);
            }
        }
        $unknown = $this->unknownDocumentPaths($document, '', $knownPaths, $containerPaths);
        if ($unknown !== []) {
            throw DataValidationException::one(
                'unknown_fields',
                'Unknown fields: '.implode(', ', $unknown).'.',
                '$',
                meta: ['fields' => $unknown],
            );
        }

        foreach ($fields as $field) {
            if (! $field->system) {
                continue;
            }
            $incomingValues = $this->runtime->paths->values($incoming, $field->path);
            $changed = $beforeValues[$field->id] !== $documentValues[$field->id];
            if ($incomingValues !== [] || $changed) {
                throw DataValidationException::one('system_field', 'System-owned fields cannot be written.', $field->path);
            }
        }
    }

    /**
     * @param  array<string,true>  $knownPaths
     * @param  array<string,true>  $containerPaths
     * @return list<string>
     */
    private function unknownDocumentPaths(
        mixed $node,
        string $parentPath,
        array $knownPaths,
        array $containerPaths,
    ): array {
        if (! is_array($node)) {
            return [];
        }
        if (array_is_list($node)) {
            $unknown = [];
            foreach ($node as $item) {
                array_push($unknown, ...$this->unknownDocumentPaths(
                    $item,
                    $parentPath,
                    $knownPaths,
                    $containerPaths,
                ));
            }

            return array_values(array_unique($unknown));
        }

        $unknown = [];
        foreach ($node as $key => $value) {
            if ($key === '_item_id') {
                continue;
            }
            $path = $parentPath === '' ? (string) $key : $parentPath.'.'.$key;
            if (! isset($knownPaths[$path]) && ! isset($containerPaths[$path])) {
                $unknown[] = $path;

                continue;
            }
            if (isset($containerPaths[$path])) {
                array_push($unknown, ...$this->unknownDocumentPaths(
                    $value,
                    $path,
                    $knownPaths,
                    $containerPaths,
                ));
            }
        }

        return array_values(array_unique($unknown));
    }

    /** @param list<FieldDefinition> $fields @return array<string,mixed> */
    private function normalizeAndValidate(array $document, array $fields): array
    {
        foreach ($fields as $field) {
            if ($field->system) {
                continue;
            }
            $handler = $this->runtime->types->get($field->type);
            $values = $this->runtime->paths->values($document, $field->path);
            if ($values === []) {
                $handler->validateValue(null, $field, '$.'.$field->path);

                continue;
            }

            $document = $this->runtime->paths->map(
                $document,
                $field->path,
                static function (mixed $value, string $occurrence) use ($handler, $field): mixed {
                    $normalized = $handler->normalize($value, $field, $occurrence);
                    $handler->validateValue($normalized, $field, $occurrence);

                    return $normalized;
                },
            );
        }

        return $document;
    }

    /** @param list<FieldDefinition> $fields */
    private function resolveAndValidateDependencies(array $document, array $fields): void
    {
        $set = new DependencySet;
        foreach ($fields as $field) {
            $handler = $this->runtime->types->get($field->type);
            foreach ($this->runtime->paths->values($document, $field->path) as $value) {
                $handler->collectBatchDependencies($value['value'], $field, $value['occurrence'], $set);
            }
        }

        $resolved = $this->runtime->dependencies->resolve($set);
        foreach ($fields as $field) {
            $handler = $this->runtime->types->get($field->type);
            foreach ($this->runtime->paths->values($document, $field->path) as $value) {
                $handler->validateResolvedDependencies($value['value'], $field, $value['occurrence'], $resolved);
            }
        }

    }

    /** @param list<FieldDefinition> $fields @param list<string> $changedFieldIds */
    private function buildChangeSet(
        array $before,
        array $document,
        array $fields,
        array $changedFieldIds,
        RecordWriteCommand $command,
        string $schemaVersionId,
    ): RecordChangeSet {
        $projections = $this->runtime->projectionChanges->build(
            $command->recordDefinitionId,
            $schemaVersionId,
            $document,
            $fields,
        );
        $refEdgesByField = [];
        foreach ($projections->refEdges as $edge) {
            $refEdgesByField[(string) $edge['field_id']][] = $edge;
        }
        $mediaEdgesByField = [];
        foreach ($projections->mediaEdges as $edge) {
            $mediaEdgesByField[(string) $edge['field_id']][] = $edge;
        }
        foreach ($fields as $field) {
            $targetIds = array_values(array_unique(array_map(
                static fn (array $edge): int => (int) $edge['target_record_id'],
                $refEdgesByField[$field->id] ?? [],
            )));
            if ($targetIds !== []) {
                $readable = array_fill_keys($this->access->readableTargetRecordIds($command->actorId, $targetIds), true);
                $attachable = array_fill_keys($this->access->attachableRecordIds(
                    $command->actorId, $command->recordDefinitionId, $field, $targetIds,
                ), true);
                foreach ($targetIds as $targetId) {
                    if (! isset($readable[$targetId], $attachable[$targetId])) {
                        throw DataAccessDenied::for('record.'.$targetId, 'attach');
                    }
                }
            }
            $mediaIds = array_values(array_unique(array_map(
                static fn (array $edge): string => (string) $edge['media_id'],
                $mediaEdgesByField[$field->id] ?? [],
            )));
            if ($mediaIds !== []) {
                $readable = array_fill_keys($this->access->readableMediaIds($command->actorId, $mediaIds), true);
                $attachable = array_fill_keys($this->access->attachableMediaIds(
                    $command->actorId, $command->recordDefinitionId, $field, $mediaIds,
                ), true);
                foreach ($mediaIds as $mediaId) {
                    if (! isset($readable[$mediaId], $attachable[$mediaId])) {
                        throw DataAccessDenied::for('media.'.$mediaId, 'attach');
                    }
                }
            }
        }

        $noOp = $command->recordId !== null
            && $this->runtime->canonicalJson->encode($before) === $this->runtime->canonicalJson->encode($document);

        return new RecordChangeSet(
            changedFieldIds: $changedFieldIds,
            projections: $projections,
            events: $noOp ? [] : [[
                'type' => $command->recordId === null
                    ? RecordEventType::Created->value
                    : RecordEventType::Updated->value,
                'changed_field_ids' => $changedFieldIds,
            ]],
            noOp: $noOp,
        );
    }

    /**
     * @param  list<FieldDefinition>  $fields
     * @param  array<string,string>  $beforeValues
     * @param  array<string,string>  $documentValues
     * @return list<string>
     */
    private function changedFieldIds(array $fields, array $beforeValues, array $documentValues): array
    {
        $changed = [];
        foreach ($fields as $field) {
            if ($beforeValues[$field->id] !== $documentValues[$field->id]) {
                $changed[] = $field->id;
            }
        }

        return $changed;
    }

    /** @param list<FieldDefinition> $fields @return array<string,string> */
    private function encodedFieldValues(array $document, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $values[$field->id] = $this->runtime->canonicalJson->encode(
                $this->runtime->paths->values($document, $field->path),
            );
        }

        return $values;
    }

    private function writeAuditAndOutbox(
        string $operationId,
        RecordWriteCommand $command,
        int $recordId,
        int $revision,
        RecordChangeSet $changes,
    ): void {
        $events = array_map(
            static fn (array $event): RecordEventMessage => new RecordEventMessage(
                type: (string) $event['type'],
                payload: [
                    'record_id' => $recordId,
                    'record_definition_id' => $command->recordDefinitionId,
                    'revision' => $revision,
                    'changed_field_ids' => $event['changed_field_ids'],
                ],
            ),
            $changes->events,
        );
        $this->runtime->recordEvents->append(new RecordAuditEntry(
            operationId: $operationId,
            command: $command->kind(),
            recordId: $recordId,
            actorId: $command->actorId,
            revision: $revision,
            changedFieldIds: $changes->changedFieldIds,
            metadata: ['idempotency_key_present' => $command->idempotencyKey !== null],
        ), $events);
    }

    private function requestHash(RecordWriteCommand $command): string
    {
        return $this->runtime->canonicalJson->hash([
            'definition_id' => $command->recordDefinitionId,
            'record_id' => $command->recordId,
            'expected_revision' => $command->expectedRevision,
            'schema_version_id' => $command->schemaVersionId,
            'replace' => $command->replace,
            'schema_migration' => $command->schemaMigration,
            'document' => $command->document,
        ]);
    }

    /** @return array<string,mixed> */
    private function decodeDocument(mixed $value): array
    {
        return $this->runtime->json->decodeMap($value, 'record JSON document');
    }
}
