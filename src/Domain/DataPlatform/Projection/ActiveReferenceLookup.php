<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Owns the active-source predicate and public identity of incoming edges. */
final class ActiveReferenceLookup
{
    /** @return Collection<int,object> */
    public function toRecord(int $recordId): Collection
    {
        return $this->query('dp_ref_edges', 'target_record_id', $recordId)
            ->get(['edge.source_record_id', 'edge.field_id', 'edge.deletion_policy']);
    }

    /** @return Collection<int,object> */
    public function toMedia(string $mediaId): Collection
    {
        return $this->query('dp_media_edges', 'media_id', $mediaId)
            ->get(['edge.source_record_id', 'edge.field_id']);
    }

    /**
     * @param  iterable<object>  $edges
     * @return list<array{source_record_id:int,field_id:string}>
     */
    public function present(iterable $edges): array
    {
        return collect($edges)->map(static fn (object $edge): array => [
            'source_record_id' => (int) $edge->source_record_id,
            'field_id' => (string) $edge->field_id,
        ])->values()->all();
    }

    private function query(string $table, string $targetColumn, int|string $targetId): Builder
    {
        return DB::table($table.' as edge')
            ->join('dp_records as source', 'source.id', '=', 'edge.source_record_id')
            ->where('edge.'.$targetColumn, $targetId)
            ->whereNull('source.deleted_at')
            ->orderBy('source.id');
    }
}
