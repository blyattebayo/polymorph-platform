<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Read;

use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

/** Converts a selected dp_records row into the stable public scalar contract. */
final readonly class RecordRowPresenter
{
    public function __construct(private DatabaseJson $json) {}

    /** @return array{id:int,record_definition_id:int,schema_version_id:string,data:array<string,mixed>,revision:int,author_id:int|null,created_at:string,updated_at:string} */
    public function present(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'record_definition_id' => (int) $row->record_definition_id,
            'schema_version_id' => (string) $row->schema_version_id,
            'data' => $this->json->decodeMap($row->data, 'dp_records.data'),
            'revision' => (int) $row->revision,
            'author_id' => $row->author_id === null ? null : (int) $row->author_id,
            'created_at' => (string) $row->created_at,
            'updated_at' => (string) $row->updated_at,
        ];
    }
}
