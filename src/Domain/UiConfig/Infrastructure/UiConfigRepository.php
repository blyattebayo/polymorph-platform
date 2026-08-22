<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Infrastructure;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\UiConfig\Core\Exceptions\UiConfigVersionDowngradeException;
use Polymorph\Platform\Domain\UiConfig\Core\Models\UiConfig;
use Polymorph\Platform\PipelineCore\Locking\LockKey;
use Polymorph\Platform\PipelineCore\Locking\LockManager;

final class UiConfigRepository
{
    public function __construct(
        private readonly LockManager $locks,
    ) {}

    public function find(string $namespace, string $key, ?int $userId): ?UiConfig
    {
        return $this->queryFor($namespace, $key, $userId)->first();
    }

    public function save(
        string $namespace,
        string $key,
        ?int $userId,
        int $version,
        string $documentJson,
    ): UiConfig {
        return DB::transaction(function () use ($namespace, $key, $userId, $version, $documentJson): UiConfig {
            $this->locks->acquireLock($this->lockKey($namespace, $key, $userId));
            $config = $this->queryFor($namespace, $key, $userId)->firstOrNew();

            if ($config->exists && $config->version > $version) {
                throw new UiConfigVersionDowngradeException($config->version, $version);
            }

            $config->fill([
                'namespace' => $namespace,
                'key' => $key,
                'user_id' => $userId,
                'version' => $version,
                'document' => $documentJson,
            ]);
            $config->save();

            return $config;
        });
    }

    public function delete(string $namespace, string $key, ?int $userId): void
    {
        DB::transaction(function () use ($namespace, $key, $userId): void {
            $this->locks->acquireLock($this->lockKey($namespace, $key, $userId));
            $this->queryFor($namespace, $key, $userId)->delete();
        });
    }

    private function lockKey(string $namespace, string $key, ?int $userId): LockKey
    {
        return new LockKey(
            'ui-config',
            "{$namespace}/{$key}",
            $userId === null ? 'global' : "user/{$userId}",
        );
    }

    /** @return Builder<UiConfig> */
    private function queryFor(string $namespace, string $key, ?int $userId): Builder
    {
        return UiConfig::query()
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->where('user_id', $userId);
    }
}
