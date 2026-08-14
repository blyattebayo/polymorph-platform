<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Read;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformResourceNotFound;

/** Performs transport-independent existence checks without exposing record payloads. */
final class RecordLocator
{
    public function assertDefinitionExists(int $definitionId): void
    {
        if (! DB::table('dp_record_definitions')->where('id', $definitionId)->exists()) {
            throw DataPlatformResourceNotFound::for('record-definition', $definitionId);
        }
    }

    public function assertRecordExists(int $recordId): void
    {
        if (! DB::table('dp_records')->where('id', $recordId)->exists()) {
            throw DataPlatformResourceNotFound::for('record', $recordId);
        }
    }

    /** @param list<int> $recordIds */
    public function assertRecordsExist(array $recordIds): void
    {
        $recordIds = array_values(array_unique($recordIds));
        if ($recordIds === [] || DB::table('dp_records')->whereIn('id', $recordIds)->count() !== count($recordIds)) {
            throw DataPlatformResourceNotFound::for('record-set', implode(',', $recordIds));
        }
    }
}
