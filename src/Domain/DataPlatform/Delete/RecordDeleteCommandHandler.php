<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Delete;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Polymorph\Platform\Domain\DataPlatform\Access\DataAccessDenied;
use Polymorph\Platform\Domain\DataPlatform\Access\DataAccessPolicy;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformInvariantViolation;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformResourceNotFound;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformStateConflict;
use Polymorph\Platform\Domain\DataPlatform\Fields\Cardinality;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldType;
use Polymorph\Platform\Domain\DataPlatform\Fields\ReferenceDeletionPolicy;
use Polymorph\Platform\Domain\DataPlatform\Outbox\RecordAuditEntry;
use Polymorph\Platform\Domain\DataPlatform\Outbox\RecordEventMessage;
use Polymorph\Platform\Domain\DataPlatform\Outbox\RecordEventType;
use Polymorph\Platform\Domain\DataPlatform\Projection\ActiveReferenceLookup;
use Polymorph\Platform\Domain\DataPlatform\Write\MaintenanceRecordCommandBus;
use Polymorph\Platform\Domain\DataPlatform\Write\OptimisticLockConflict;
use Polymorph\Platform\Domain\DataPlatform\Write\RecordCommandRuntime;
use Polymorph\Platform\Domain\DataPlatform\Write\RecordWriteCommand;

/** Applies reverse-reference policies and soft-deletes records in one transaction. */
final class RecordDeleteCommandHandler
{
    public function __construct(
        private readonly DataAccessPolicy $access,
        private readonly RecordCommandRuntime $runtime,
        private readonly MaintenanceRecordCommandBus $maintenanceWrites,
        private readonly ActiveReferenceLookup $references,
    ) {}

    public function execute(RecordDeleteCommand $command): RecordDeleteResult
    {
        return DB::transaction(function () use ($command): RecordDeleteResult {
            $definitionId = DB::table('dp_records')->where('id', $command->recordId)->value('record_definition_id');
            if ($definitionId === null) {
                throw DataPlatformResourceNotFound::for('record', $command->recordId);
            }
            $this->lockAffectedDefinitions($command->recordId, (int) $definitionId);
            $root = DB::table('dp_records')->where('id', $command->recordId)->lockForUpdate()->first();
            if ($root === null) {
                throw DataPlatformResourceNotFound::for('record', $command->recordId);
            }
            $definitionId = (int) $root->record_definition_id;
            if (! $this->access->canDeleteRecord($command->actorId, $definitionId, $command->recordId)) {
                throw DataAccessDenied::for('record.'.$command->recordId, 'delete');
            }

            $requestHash = $this->requestHash($command);
            $replayed = $this->runtime->idempotency->claim(
                $command->actorId,
                $command->kind(),
                $command->idempotencyKey,
                $requestHash,
            );
            if ($replayed !== null) {
                return new RecordDeleteResult(
                    (int) $replayed['record_id'],
                    (int) $replayed['revision'],
                    array_map('intval', $replayed['deleted_record_ids'] ?? []),
                    true,
                );
            }

            if ($root->deleted_at !== null) {
                if ((int) $root->revision !== $command->expectedRevision) {
                    throw new OptimisticLockConflict(
                        $command->recordId,
                        $command->expectedRevision,
                        (int) $root->revision,
                    );
                }
                $result = new RecordDeleteResult(
                    $command->recordId,
                    (int) $root->revision,
                    [$command->recordId],
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
            if ((int) $root->revision !== $command->expectedRevision) {
                throw new OptimisticLockConflict(
                    $command->recordId,
                    $command->expectedRevision,
                    (int) $root->revision,
                );
            }

            $deleted = [];
            $visited = [];
            $maxCascadeDepth = max(0, (int) config('data_platform.delete.max_cascade_depth'));
            $rootRevision = $this->deleteRecursive(
                $command->recordId,
                $command->actorId,
                $visited,
                $deleted,
                $command->expectedRevision,
                0,
                $maxCascadeDepth,
            );
            sort($deleted);
            $result = new RecordDeleteResult($command->recordId, $rootRevision, $deleted);
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

    /** @param array<int,true> $visited @param list<int> $deleted */
    private function deleteRecursive(
        int $recordId,
        ?int $actorId,
        array &$visited,
        array &$deleted,
        ?int $requiredRevision,
        int $depth,
        int $maxDepth,
    ): int {
        if (isset($visited[$recordId])) {
            return (int) DB::table('dp_records')->where('id', $recordId)->value('revision');
        }
        if ($depth > $maxDepth) {
            throw DataPlatformStateConflict::because(
                'delete_cascade_depth_exceeded',
                "Delete cascade exceeds the configured maximum depth of {$maxDepth}.",
                ['record_id' => $recordId, 'maximum_depth' => $maxDepth],
            );
        }
        $visited[$recordId] = true;

        $record = DB::table('dp_records')->where('id', $recordId)->lockForUpdate()->first();
        if ($record === null) {
            throw DataPlatformResourceNotFound::for('record', $recordId);
        }
        if ($record->deleted_at !== null) {
            return (int) $record->revision;
        }
        $revision = (int) $record->revision;
        if ($requiredRevision !== null && $requiredRevision !== $revision) {
            throw new OptimisticLockConflict($recordId, $requiredRevision, $revision);
        }
        $definitionId = (int) $record->record_definition_id;
        if (! $this->access->canDeleteRecord($actorId, $definitionId, $recordId)) {
            throw DataAccessDenied::for('record.'.$recordId, 'delete');
        }

        $incoming = $this->references->toRecord($recordId);

        $restrictedIncoming = collect();
        $cascadeIncoming = collect();
        $nullifyIncoming = collect();
        foreach ($incoming as $edge) {
            match ($this->referenceDeletionPolicy($edge)) {
                ReferenceDeletionPolicy::Restrict => $restrictedIncoming->push($edge),
                ReferenceDeletionPolicy::Cascade => $cascadeIncoming->push($edge),
                ReferenceDeletionPolicy::Nullify => $nullifyIncoming->push($edge),
                ReferenceDeletionPolicy::PreserveTombstone => null,
            };
        }

        $restricted = $this->references->present($restrictedIncoming);
        if ($restricted !== []) {
            $sourceIds = array_values(array_unique(array_column($restricted, 'source_record_id')));
            $readable = array_fill_keys($this->access->readableTargetRecordIds($actorId, $sourceIds), true);
            $visible = array_values(array_filter(
                $restricted,
                static fn (array $reference): bool => isset($readable[$reference['source_record_id']]),
            ));
            throw new RecordDeleteRestricted($recordId, $visible, count($restricted) - count($visible));
        }

        // preserve_tombstone deliberately keeps its edge; hydration will expose
        // the target as deleted while retaining the original identity.
        $cascadeSources = $cascadeIncoming
            ->pluck('source_record_id')->map(static fn (mixed $id): int => (int) $id)->unique()->values()->all();
        foreach ($cascadeSources as $sourceId) {
            $this->deleteRecursive($sourceId, $actorId, $visited, $deleted, null, $depth + 1, $maxDepth);
        }

        $nullifyGroups = $nullifyIncoming
            ->reject(static fn (object $edge): bool => in_array((int) $edge->source_record_id, $cascadeSources, true))
            ->groupBy('source_record_id');
        foreach ($nullifyGroups as $sourceId => $edges) {
            $fieldIds = $edges->pluck('field_id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->unique()->values()->all();
            $this->nullifySource((int) $sourceId, $recordId, $fieldIds, $actorId);
        }

        $operationId = (string) Str::uuid();
        $nextRevision = $revision + 1;
        $affected = DB::table('dp_records')
            ->where('id', $recordId)
            ->where('revision', $revision)
            ->whereNull('deleted_at')
            ->update([
                'revision' => $nextRevision,
                'author_id' => $actorId,
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
        if ($affected !== 1) {
            $actual = (int) DB::table('dp_records')->where('id', $recordId)->value('revision');
            throw new OptimisticLockConflict($recordId, $revision, $actual);
        }

        // Deleted records no longer reserve values; relationship projections remain as tombstone evidence.
        $this->runtime->projections->releaseUniqueValues($recordId);
        $this->runtime->recordEvents->append(new RecordAuditEntry(
            operationId: $operationId,
            command: RecordDeleteCommand::KIND,
            recordId: $recordId,
            actorId: $actorId,
            revision: $nextRevision,
            changedFieldIds: [],
            metadata: ['soft_delete' => true],
        ), [new RecordEventMessage(RecordEventType::Deleted->value, [
            'record_id' => $recordId,
            'record_definition_id' => $definitionId,
            'revision' => $nextRevision,
            'data' => $this->runtime->json->decodeMap($record->data, 'dp_records.data'),
        ])]);
        $deleted[] = $recordId;

        return $nextRevision;
    }

    /** @param list<string> $fieldIds */
    private function nullifySource(int $sourceId, int $targetId, array $fieldIds, ?int $actorId): void
    {
        $source = DB::table('dp_records')->where('id', $sourceId)->lockForUpdate()->first();
        if ($source === null || $source->deleted_at !== null) {
            return;
        }
        $document = $this->runtime->json->decodeMap($source->data, 'dp_records.data');
        $fields = collect($this->runtime->schemas->fields((string) $source->schema_version_id))->keyBy('id');

        foreach ($fieldIds as $fieldId) {
            /** @var FieldDefinition|null $field */
            $field = $fields->get($fieldId);
            if ($field === null || $field->type !== FieldType::REF) {
                throw DataPlatformInvariantViolation::because(
                    'reference_projection_field_missing',
                    "Reference projection {$fieldId} has no matching schema field.",
                    ['field_id' => $fieldId],
                );
            }
            $document = $this->runtime->paths->map(
                $document,
                $field->path,
                static function (mixed $value) use ($field, $targetId): mixed {
                    if ($field->cardinality === Cardinality::MANY) {
                        return array_values(array_filter(
                            (array) $value,
                            static fn (mixed $id): bool => (int) $id !== $targetId,
                        ));
                    }

                    return (int) $value === $targetId ? null : $value;
                },
            );
        }

        $schemaVersionId = (string) $source->schema_version_id;
        // Nullification is a system-owned consequence of the surrounding
        // delete transaction, not an actor-authored update. Always use the
        // maintenance path so published, backfilling, and archived stored
        // versions share the same authority and document/field schema.
        $command = new RecordWriteCommand(
            recordDefinitionId: (int) $source->record_definition_id,
            document: $document,
            actorId: $actorId,
            recordId: $sourceId,
            expectedRevision: (int) $source->revision,
            schemaVersionId: $schemaVersionId,
            replace: true,
            schemaMigration: true,
        );
        $this->maintenanceWrites->dispatch($command);
    }

    private function referenceDeletionPolicy(object $edge): ReferenceDeletionPolicy
    {
        $raw = $edge->deletion_policy ?? null;
        $policy = is_string($raw) ? ReferenceDeletionPolicy::tryFrom($raw) : null;
        if ($policy === null) {
            throw DataPlatformInvariantViolation::because(
                'invalid_reference_deletion_policy',
                'A reference projection contains an unknown deletion policy.',
                ['deletion_policy' => $raw],
            );
        }

        return $policy;
    }

    private function lockAffectedDefinitions(int $rootRecordId, int $rootDefinitionId): void
    {
        $affectedRecordIds = [$rootRecordId => true];
        $visited = [];
        $pending = [$rootRecordId];
        while ($pending !== []) {
            $targetId = array_pop($pending);
            if (isset($visited[$targetId])) {
                continue;
            }
            $visited[$targetId] = true;

            foreach ($this->references->toRecord($targetId) as $edge) {
                $sourceId = (int) $edge->source_record_id;
                $policy = $this->referenceDeletionPolicy($edge);
                if ($policy === ReferenceDeletionPolicy::Restrict
                    || $policy === ReferenceDeletionPolicy::PreserveTombstone) {
                    continue;
                }
                $affectedRecordIds[$sourceId] = true;
                if ($policy === ReferenceDeletionPolicy::Cascade) {
                    $pending[] = $sourceId;
                }
            }
        }

        $definitionIds = DB::table('dp_records')
            ->whereIn('id', array_keys($affectedRecordIds))
            ->whereNull('deleted_at')
            ->pluck('record_definition_id')
            ->map('intval')
            ->push($rootDefinitionId)
            ->unique()
            ->sort()
            ->values()
            ->all();

        DB::table('dp_record_definitions')
            ->whereIn('id', $definitionIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    private function requestHash(RecordDeleteCommand $command): string
    {
        return $this->runtime->canonicalJson->hash([
            'record_id' => $command->recordId,
            'expected_revision' => $command->expectedRevision,
        ]);
    }
}
