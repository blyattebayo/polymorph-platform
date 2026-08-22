<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Infrastructure;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\EntryView\Core\Models\EntryViewConfig;
use Polymorph\Platform\Domain\UiConfig\Core\Exceptions\UiConfigVersionDowngradeException;
use Polymorph\Platform\PipelineCore\Locking\LockKey;
use Polymorph\Platform\PipelineCore\Locking\LockManager;

final class EntryViewConfigRepository
{
    public function __construct(
        private readonly LockManager $locks,
    ) {}

    public function find(int $recordDefinitionId, int $schemaId): ?EntryViewConfig
    {
        return $this->queryFor($recordDefinitionId, $schemaId)->first();
    }

    public function save(
        int $recordDefinitionId,
        int $schemaId,
        int $version,
        string $documentJson,
    ): EntryViewConfig {
        return DB::transaction(function () use ($recordDefinitionId, $schemaId, $version, $documentJson): EntryViewConfig {
            $this->locks->acquireLock($this->lockKey($recordDefinitionId, $schemaId));
            $config = $this->queryFor($recordDefinitionId, $schemaId)->firstOrNew();

            if ($config->exists && $config->version > $version) {
                throw new UiConfigVersionDowngradeException($config->version, $version);
            }

            $config->fill([
                'record_definition_id' => $recordDefinitionId,
                'schema_id' => $schemaId,
                'version' => $version,
                'document' => $documentJson,
            ]);
            $config->save();

            return $config;
        });
    }

    public function delete(int $recordDefinitionId, int $schemaId): void
    {
        DB::transaction(function () use ($recordDefinitionId, $schemaId): void {
            $this->locks->acquireLock($this->lockKey($recordDefinitionId, $schemaId));
            $this->queryFor($recordDefinitionId, $schemaId)->delete();
        });
    }

    private function lockKey(int $recordDefinitionId, int $schemaId): LockKey
    {
        return new LockKey('entry-view-config', (string) $recordDefinitionId, (string) $schemaId);
    }

    /** @return Builder<EntryViewConfig> */
    private function queryFor(int $recordDefinitionId, int $schemaId): Builder
    {
        return EntryViewConfig::query()
            ->where('record_definition_id', $recordDefinitionId)
            ->where('schema_id', $schemaId);
    }
}
