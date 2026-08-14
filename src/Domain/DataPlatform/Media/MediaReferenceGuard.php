<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Media;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformInvariantViolation;
use Polymorph\Platform\Domain\DataPlatform\Projection\ProjectionStore;

final class MediaReferenceGuard
{
    private bool $tablesKnownAvailable = false;

    public function __construct(private readonly ProjectionStore $projections) {}

    public function assertCanForceDelete(string $mediaId): void
    {
        if (! $this->tablesAvailable()) {
            throw DataPlatformInvariantViolation::because(
                'media_reference_tables_missing',
                'Media reference protection requires Data Platform reference tables.',
            );
        }

        $references = DB::table('dp_media_edges as edge')
            ->join('dp_records as source', 'source.id', '=', 'edge.source_record_id')
            ->where('edge.media_id', $mediaId)
            ->whereNull('source.deleted_at')
            ->orderBy('source.id')
            ->get(['edge.source_record_id', 'edge.field_id'])
            ->map(static fn (object $edge): array => [
                'source_record_id' => (int) $edge->source_record_id,
                'field_id' => (string) $edge->field_id,
            ])->all();

        if ($references !== []) {
            throw new MediaHasActiveReferences($mediaId, $references);
        }
    }

    /** Removes projection edges whose source is already a record tombstone. */
    public function pruneInactiveReferences(string $mediaId): void
    {
        if (! $this->tablesAvailable()) {
            throw DataPlatformInvariantViolation::because(
                'media_reference_tables_missing',
                'Media reference cleanup requires Data Platform reference tables.',
            );
        }

        $this->projections->pruneInactiveMediaReferences($mediaId);
    }

    private function tablesAvailable(): bool
    {
        if ($this->tablesKnownAvailable) {
            return true;
        }

        return $this->tablesKnownAvailable = Schema::hasTable('dp_media_edges') && Schema::hasTable('dp_records');
    }
}
