<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind;

final class BatchDependencyResolver
{
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
        $media = $mediaIds === []
            ? []
            : DB::table('media as m')
                ->leftJoin('media_images as mi', 'mi.media_id', '=', 'm.id')
                ->leftJoin('media_av_metadata as mav', 'mav.media_id', '=', 'm.id')
                ->leftJoin('dp_media_processing_states as mps', 'mps.media_id', '=', 'm.id')
                ->whereIn('m.id', $mediaIds)
                ->orderBy('m.id')
                ->lock('for share of m')
                ->get([
                    'm.id', 'm.mime', 'm.size_bytes', 'm.deleted_at',
                    'mi.width', 'mi.height', 'mav.duration_ms',
                    DB::raw("coalesce(mps.state, 'ready') as processing_state"),
                ])
                ->mapWithKeys(static function (object $row): array {
                    $data = (array) $row;
                    $data['kind'] = MediaKind::fromMime((string) $row->mime)->value;

                    return [(string) $row->id => $data];
                })
                ->all();

        return new ResolvedDependencies($records, $media);
    }
}
