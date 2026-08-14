<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

/** Sole persistence boundary for synchronous record projections. */
final class ProjectionStore
{
    public function __construct(private readonly DatabaseJson $json) {}

    public function replace(int $recordId, int $definitionId, ProjectionChangeSet $changes): void
    {
        DB::table('dp_ref_edges')->where('source_record_id', $recordId)->delete();
        DB::table('dp_media_edges')->where('source_record_id', $recordId)->delete();
        DB::table('dp_unique_values')->where('record_id', $recordId)->delete();

        $now = now();
        $refEdges = array_map(
            static fn (array $edge): array => [
                ...$edge,
                'source_record_id' => $recordId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $changes->refEdges,
        );
        if ($refEdges !== []) {
            DB::table('dp_ref_edges')->insert($refEdges);
        }
        $mediaEdges = array_map(
            fn (array $edge): array => [
                ...$edge,
                'attachment' => $this->json->encode((array) $edge['attachment']),
                'source_record_id' => $recordId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $changes->mediaEdges,
        );
        if ($mediaEdges !== []) {
            DB::table('dp_media_edges')->insert($mediaEdges);
        }
        $uniqueValues = array_map(
            fn (array $unique): array => [
                ...$unique,
                'record_definition_id' => $definitionId,
                'record_id' => $recordId,
                'value' => $this->json->encode($unique['value']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $changes->uniqueValues,
        );
        if ($uniqueValues !== []) {
            DB::table('dp_unique_values')->insert($uniqueValues);
        }

        DB::table('dp_search_documents')->updateOrInsert(['record_id' => $recordId], static fn (bool $exists): array => [
            'content' => implode("\n", $changes->searchValues),
            'projection_version' => 1,
            ...($exists ? [] : ['created_at' => $now]),
            'updated_at' => $now,
        ]);
        DB::table('dp_display_values')->updateOrInsert(['record_id' => $recordId], static fn (bool $exists): array => [
            'value' => $changes->displayValue ?? "Record #{$recordId}",
            'projection_version' => 1,
            ...($exists ? [] : ['created_at' => $now]),
            'updated_at' => $now,
        ]);
    }

    public function releaseUniqueValues(int $recordId): void
    {
        DB::table('dp_unique_values')->where('record_id', $recordId)->delete();
    }

    public function pruneInactiveMediaReferences(string $mediaId): void
    {
        $inactiveRecordIds = DB::table('dp_records')
            ->whereNotNull('deleted_at')
            ->select('id');

        DB::table('dp_media_edges')
            ->where('media_id', $mediaId)
            ->whereIn('source_record_id', $inactiveRecordIds)
            ->delete();
    }
}
