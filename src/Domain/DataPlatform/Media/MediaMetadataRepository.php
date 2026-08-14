<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Media;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind;

/** Shared media metadata lookup; write callers explicitly request a shared lock. */
final class MediaMetadataRepository
{
    /** @param list<string> $ids @return array<string,array<string,mixed>> */
    public function findMany(array $ids, bool $lockForShare = false): array
    {
        if ($ids === []) {
            return [];
        }

        $query = DB::table('media as m')
            ->leftJoin('media_images as mi', 'mi.media_id', '=', 'm.id')
            ->leftJoin('media_av_metadata as mav', 'mav.media_id', '=', 'm.id')
            ->leftJoin('dp_media_processing_states as mps', 'mps.media_id', '=', 'm.id')
            ->whereIn('m.id', $ids)
            ->orderBy('m.id');
        if ($lockForShare) {
            $query->lock('for share of m');
        }

        return $query->get([
            'm.id', 'm.mime', 'm.size_bytes', 'm.title', 'm.alt', 'm.deleted_at',
            'mi.width', 'mi.height', 'mav.duration_ms',
            DB::raw("coalesce(mps.state, 'ready') as processing_state"),
        ])->mapWithKeys(static function (object $row): array {
            $data = (array) $row;
            $data['kind'] = MediaKind::fromMime((string) $row->mime)->value;

            return [(string) $row->id => $data];
        })->all();
    }
}
