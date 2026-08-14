<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Read;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

/** Maps active record rows for use cases that need explicit lock semantics. */
final class RecordStore
{
    public function __construct(private readonly DatabaseJson $json) {}

    public function findActive(int $recordId): ?StoredRecord
    {
        return $this->map($this->query($recordId)->first());
    }

    public function lockActive(int $recordId): ?StoredRecord
    {
        return $this->map($this->query($recordId)->lockForUpdate()->first());
    }

    private function query(int $recordId): Builder
    {
        return DB::table('dp_records')->where('id', $recordId)->whereNull('deleted_at');
    }

    private function map(?object $row): ?StoredRecord
    {
        if ($row === null) {
            return null;
        }

        return new StoredRecord(
            id: (int) $row->id,
            definitionId: (int) $row->record_definition_id,
            schemaVersionId: (string) $row->schema_version_id,
            document: $this->json->decodeMap($row->data, 'dp_records.data'),
            revision: (int) $row->revision,
        );
    }
}
