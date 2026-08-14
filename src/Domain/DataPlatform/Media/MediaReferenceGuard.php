<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Media;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Polymorph\Platform\Domain\DataPlatform\Access\DataAccessPolicy;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformInvariantViolation;
use Polymorph\Platform\Domain\DataPlatform\Projection\ActiveReferenceLookup;

final class MediaReferenceGuard
{
    private ?bool $referenceTablesAvailable = null;

    public function __construct(
        private readonly DataAccessPolicy $access,
        private readonly ActiveReferenceLookup $references,
    ) {}

    public function assertCanForceDelete(string $mediaId, ?int $actorId = null): void
    {
        $this->assertReferenceTablesAvailable('protection');

        $references = $this->references->present($this->references->toMedia($mediaId));

        if ($references !== []) {
            $sourceIds = array_values(array_unique(array_column($references, 'source_record_id')));
            $readable = array_fill_keys($this->access->readableTargetRecordIds($actorId, $sourceIds), true);
            $visible = array_values(array_filter(
                $references,
                static fn (array $reference): bool => isset($readable[$reference['source_record_id']]),
            ));
            throw new MediaHasActiveReferences($mediaId, $visible, count($references) - count($visible));
        }
    }

    /** Removes projection edges whose source is already a record tombstone. */
    public function pruneInactiveReferences(string $mediaId): void
    {
        $this->assertReferenceTablesAvailable('cleanup');

        $inactiveRecordIds = DB::table('dp_records')
            ->whereNotNull('deleted_at')
            ->select('id');

        DB::table('dp_media_edges')
            ->where('media_id', $mediaId)
            ->whereIn('source_record_id', $inactiveRecordIds)
            ->delete();
    }

    private function tablesAvailable(): bool
    {
        return $this->referenceTablesAvailable ??= Schema::hasTable('dp_media_edges')
            && Schema::hasTable('dp_records');
    }

    private function assertReferenceTablesAvailable(string $purpose): void
    {
        // Fresh migrations guarantee these tables and preflight checks them at
        // deploy time. This runtime boundary exists only to translate a damaged
        // installation into a stable domain error instead of leaking a raw
        // database exception through media deletion.
        if (! $this->tablesAvailable()) {
            throw DataPlatformInvariantViolation::because(
                'media_reference_tables_missing',
                "Media reference {$purpose} requires Data Platform reference tables.",
            );
        }
    }
}
