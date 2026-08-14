<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

use Illuminate\Database\UniqueConstraintViolationException;
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
        $refEdges = $this->rowsWithEnvelope(
            $changes->refEdges,
            ['source_record_id' => $recordId],
            $now,
        );
        if ($refEdges !== []) {
            DB::table('dp_ref_edges')->insert($refEdges);
        }
        $mediaEdges = $this->rowsWithEnvelope(
            $changes->mediaEdges,
            ['source_record_id' => $recordId],
            $now,
            fn (array $edge): array => [
                ...$edge,
                'attachment' => $this->json->encode((array) $edge['attachment']),
            ],
        );
        if ($mediaEdges !== []) {
            DB::table('dp_media_edges')->insert($mediaEdges);
        }
        $uniqueValues = $this->rowsWithEnvelope(
            $changes->uniqueValues,
            ['record_definition_id' => $definitionId, 'record_id' => $recordId],
            $now,
            fn (array $unique): array => [
                ...$unique,
                'value' => $this->json->encode($unique['value']),
            ],
        );
        foreach ($uniqueValues as $index => $uniqueValue) {
            try {
                DB::table('dp_unique_values')->insert($uniqueValue);
            } catch (UniqueConstraintViolationException $exception) {
                $candidate = $changes->uniqueValues[$index];
                throw new UniqueValueConflict(
                    (string) $candidate['field_id'],
                    $candidate['value'],
                    $exception,
                );
            }
        }

        DB::table('dp_search_documents')->updateOrInsert(['record_id' => $recordId], static fn (bool $exists): array => [
            'content' => implode("\n", $changes->searchValues),
            'projection_version' => $changes->searchProjectionVersion,
            ...($exists ? [] : ['created_at' => $now]),
            'updated_at' => $now,
        ]);
        DB::table('dp_display_values')->updateOrInsert(['record_id' => $recordId], static fn (bool $exists): array => [
            'value' => $changes->displayValue ?? "Record #{$recordId}",
            'projection_version' => $changes->displayProjectionVersion,
            ...($exists ? [] : ['created_at' => $now]),
            'updated_at' => $now,
        ]);
    }

    public function releaseUniqueValues(int $recordId): void
    {
        DB::table('dp_unique_values')->where('record_id', $recordId)->delete();
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @param  array<string,mixed>  $envelope
     * @param  callable(array<string,mixed>):array<string,mixed>|null  $normalize
     * @return list<array<string,mixed>>
     */
    private function rowsWithEnvelope(
        array $rows,
        array $envelope,
        mixed $timestamp,
        ?callable $normalize = null,
    ): array {
        return array_map(static function (array $row) use ($envelope, $timestamp, $normalize): array {
            $row = $normalize === null ? $row : $normalize($row);

            return [
                ...$row,
                ...$envelope,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }, $rows);
    }
}
