<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Commands;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Media\MediaReferenceGuard;
use Polymorph\Platform\Domain\Media\Actions\DeleteMediaFileAction;
use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/** Permanently deletes an unreferenced media asset and its physical files. */
final readonly class ForceDeleteMediaCommand
{
    public function __construct(
        private DeleteMediaFileAction $deleteFile,
        private AppLogger $logger,
        private MediaReferenceGuard $referenceGuard,
    ) {}

    public function execute(Media $media): void
    {
        /** @var list<array{disk:string,path:string,variant_id:int|string|null}> $files */
        $files = DB::transaction(function () use ($media): array {
            $locked = DB::table('media')->where('id', (string) $media->id)->lockForUpdate()->first();
            if ($locked === null) {
                return [];
            }

            // This row lock serializes the guard with V2 attach validation,
            // which takes a shared lock on every referenced media target.
            $this->referenceGuard->assertCanForceDelete((string) $media->id);
            $this->referenceGuard->pruneInactiveReferences((string) $media->id);

            $files = [];
            foreach ($media->variants as $variant) {
                $files[] = [
                    'disk' => (string) ($variant->disk ?? $media->disk),
                    'path' => (string) $variant->path,
                    'variant_id' => $variant->id,
                ];
            }
            $files[] = [
                'disk' => (string) $media->disk,
                'path' => (string) $media->path,
                'variant_id' => null,
            ];

            // Irreversible storage deletion happens only after this database
            // transaction commits successfully.
            $media->forceDelete();

            return $files;
        });

        foreach ($files as $file) {
            try {
                $this->deleteFile->execute($file['disk'], $file['path']);
            } catch (\Throwable $exception) {
                $this->logger->warning('media.force_delete.file_delete_failed', [
                    'media_id' => (string) $media->id,
                    'variant_id' => $file['variant_id'],
                    'path' => $file['path'],
                    'exception' => $exception,
                ]);
            }
        }
    }
}
