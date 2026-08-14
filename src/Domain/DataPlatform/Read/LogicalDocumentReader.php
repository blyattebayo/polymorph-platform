<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Read;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformInvariantViolation;
use Polymorph\Platform\Domain\DataPlatform\Migration\MigrationPlanState;
use Polymorph\Platform\Domain\DataPlatform\Migration\SchemaMigrationService;

/** Materializes an old document through the contiguous migration chain without mutating storage. */
final class LogicalDocumentReader
{
    /** @var array<int,string|null> */
    private array $currentVersions = [];

    /** @var array<string,object|null> */
    private array $plans = [];

    public function __construct(private readonly SchemaMigrationService $migrations) {}

    /** @param array<string,mixed> $document @return array{document:array<string,mixed>,logical_schema_version_id:string} */
    public function current(int $definitionId, string $storedVersionId, array $document): array
    {
        $current = $this->currentVersions[$definitionId] ??= DB::table('dp_record_definitions')
            ->where('id', $definitionId)->value('current_schema_version_id');
        if (! is_string($current) || $current === '' || $current === $storedVersionId) {
            return ['document' => $document, 'logical_schema_version_id' => $storedVersionId];
        }

        $version = $storedVersionId;
        $seen = [];
        while ($version !== $current) {
            if (isset($seen[$version])) {
                throw DataPlatformInvariantViolation::because(
                    'schema_migration_chain_cycle',
                    'Schema migration chain contains a cycle.',
                    ['record_definition_id' => $definitionId, 'schema_version_id' => $version],
                );
            }
            $seen[$version] = true;
            $key = $definitionId.':'.$version;
            $plan = $this->plans[$key] ??= DB::table('dp_schema_migration_plans')
                ->where('record_definition_id', $definitionId)
                ->where('from_schema_version_id', $version)
                ->whereIn('state', MigrationPlanState::values())
                ->orderBy('created_at')
                ->first();
            if ($plan === null) {
                throw DataPlatformInvariantViolation::because(
                    'schema_migration_chain_missing',
                    "No schema migration chain from {$storedVersionId} to {$current}.",
                    ['record_definition_id' => $definitionId, 'from' => $storedVersionId, 'to' => $current],
                );
            }
            $document = $this->migrations->transform((string) $plan->id, $document);
            $version = (string) $plan->to_schema_version_id;
        }

        return ['document' => $document, 'logical_schema_version_id' => $current];
    }
}
