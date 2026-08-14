<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Media\MediaMetadataRepository;

final class BatchDependencyResolver
{
    public function __construct(private readonly MediaMetadataRepository $media) {}

    public function resolve(DependencySet $set): ResolvedDependencies
    {
        $recordIds = $set->recordIds();
        $records = $recordIds === []
            ? []
            : DB::table('dp_records')
                ->whereIn('id', $recordIds)
                ->orderBy('id')
                ->sharedLock()
                ->get(['id', 'record_definition_id', 'deleted_at'])
                ->mapWithKeys(static fn (object $row): array => [(int) $row->id => (array) $row])
                ->all();

        $mediaIds = $set->mediaIds();
        $media = $this->media->findMany($mediaIds, lockForShare: true);

        return new ResolvedDependencies($records, $media);
    }
}
