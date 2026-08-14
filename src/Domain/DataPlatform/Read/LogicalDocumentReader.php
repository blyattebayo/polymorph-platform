<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Read;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformInvariantViolation;
use Polymorph\Platform\Domain\DataPlatform\Migration\SchemaMigrationService;

/** Materializes an old document through the contiguous migration chain without mutating storage. */
final class LogicalDocumentReader
{
    public function __construct(private readonly SchemaMigrationService $migrations) {}

    /** @param array<string,mixed> $document @return array{document:array<string,mixed>,logical_schema_version_id:string} */
    public function current(int $definitionId, string $storedVersionId, array $document): array
    {
        // Do not memoize this value: the reader can live for the lifetime of a
        // queue worker, while publication changes the current version between
        // calls.
        $current = DB::table('dp_record_definitions')
            ->where('id', $definitionId)->value('current_schema_version_id');

        return $this->materialize($definitionId, $storedVersionId, $document, $current);
    }

    /**
     * Resolves one live current-version snapshot for a read batch without
     * retaining it beyond the operation (long-lived workers still observe
     * publication on the next call).
     *
     * @param  list<array{definition_id:int,stored_version_id:string,document:array<string,mixed>}>  $records
     * @return list<array{document:array<string,mixed>,logical_schema_version_id:string}>
     */
    public function currentMany(array $records): array
    {
        if ($records === []) {
            return [];
        }
        $definitionIds = array_values(array_unique(array_column($records, 'definition_id')));
        $currentVersions = DB::table('dp_record_definitions')
            ->whereIn('id', $definitionIds)
            ->pluck('current_schema_version_id', 'id')
            ->all();

        return array_map(fn (array $record): array => $this->materialize(
            $record['definition_id'],
            $record['stored_version_id'],
            $record['document'],
            $currentVersions[$record['definition_id']] ?? null,
        ), $records);
    }

    /** @param array<string,mixed> $document @return array{document:array<string,mixed>,logical_schema_version_id:string} */
    private function materialize(
        int $definitionId,
        string $storedVersionId,
        array $document,
        mixed $current,
    ): array {
        if (! is_string($current) || $current === '' || $current === $storedVersionId) {
            return ['document' => $document, 'logical_schema_version_id' => $storedVersionId];
        }

        $forwardPath = $this->pathFromAncestor($definitionId, $storedVersionId, $current);
        if ($forwardPath !== null) {
            foreach ($forwardPath as [$fromVersionId, $toVersionId]) {
                // The schema ancestry identifies the only relevant edge. A
                // definition may have another draft/validating successor from
                // the same version, so selecting merely by `from` is unsafe.
                $plan = DB::table('dp_schema_migration_plans')
                    ->where('record_definition_id', $definitionId)
                    ->where('from_schema_version_id', $fromVersionId)
                    ->where('to_schema_version_id', $toVersionId)
                    ->first();
                if ($plan === null) {
                    throw $this->missingChain($definitionId, $storedVersionId, $current);
                }
                $document = $this->migrations->transform((string) $plan->id, $document);
            }

            return ['document' => $document, 'logical_schema_version_id' => $current];
        }

        // During backfill physical rows are advanced before the definition's
        // published pointer. If the stored version descends from current, the
        // document is already in its logical target form and must not be
        // reverse-transformed.
        if ($this->pathFromAncestor($definitionId, $current, $storedVersionId) !== null) {
            return ['document' => $document, 'logical_schema_version_id' => $storedVersionId];
        }

        throw $this->missingChain($definitionId, $storedVersionId, $current);
    }

    /**
     * Returns adjacent forward edges from ancestor to descendant, or null when
     * the versions are on different branches.
     *
     * @return list<array{string,string}>|null
     */
    private function pathFromAncestor(int $definitionId, string $ancestor, string $descendant): ?array
    {
        $cursor = $descendant;
        $reversePath = [];
        $seen = [];
        while ($cursor !== $ancestor) {
            if (isset($seen[$cursor])) {
                throw DataPlatformInvariantViolation::because(
                    'schema_migration_chain_cycle',
                    'Schema version ancestry contains a cycle.',
                    ['record_definition_id' => $definitionId, 'schema_version_id' => $cursor],
                );
            }
            $seen[$cursor] = true;
            $version = DB::table('dp_schema_versions')
                ->where('id', $cursor)
                ->where('record_definition_id', $definitionId)
                ->first(['previous_version_id']);
            if ($version === null || ! is_string($version->previous_version_id) || $version->previous_version_id === '') {
                return null;
            }
            $previous = (string) $version->previous_version_id;
            $reversePath[] = [$previous, $cursor];
            $cursor = $previous;
        }

        return array_reverse($reversePath);
    }

    private function missingChain(int $definitionId, string $storedVersionId, string $current): DataPlatformInvariantViolation
    {
        return DataPlatformInvariantViolation::because(
            'schema_migration_chain_missing',
            "No schema migration chain from {$storedVersionId} to {$current}.",
            ['record_definition_id' => $definitionId, 'from' => $storedVersionId, 'to' => $current],
        );
    }
}
