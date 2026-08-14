<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Read;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Access\DataAccessPolicy;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformResourceNotFound;

/** Performs transport-independent existence checks without exposing record payloads. */
final class RecordLocator
{
    public function __construct(private readonly DataAccessPolicy $access) {}

    public function assertReadableDefinition(int $definitionId, ?int $actorId): void
    {
        if (! DB::table('dp_record_definitions')->where('id', $definitionId)->exists()
            || ! $this->access->canReadDefinition($actorId, $definitionId)) {
            throw DataPlatformResourceNotFound::for('record-definition', $definitionId);
        }
    }

    public function assertWritableDefinition(int $definitionId, ?int $actorId): void
    {
        if (! DB::table('dp_record_definitions')->where('id', $definitionId)->exists()
            || ! $this->access->canWriteDefinition($actorId, $definitionId)) {
            throw DataPlatformResourceNotFound::for('record-definition', $definitionId);
        }
    }

    public function assertRecordExists(int $recordId): void
    {
        if (! DB::table('dp_records')->where('id', $recordId)->exists()) {
            throw DataPlatformResourceNotFound::for('record', $recordId);
        }
    }

    public function assertDeletableRecord(int $recordId, ?int $actorId): void
    {
        $record = DB::table('dp_records')->where('id', $recordId)->first(['record_definition_id']);
        if ($record === null
            || ! $this->access->canDeleteRecord($actorId, (int) $record->record_definition_id, $recordId)) {
            throw DataPlatformResourceNotFound::for('record', $recordId);
        }
    }
}
